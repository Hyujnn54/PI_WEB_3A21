<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Candidate_skill;
use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Repository\Candidate_skillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CandidateController extends AbstractController
{
    #[Route('/candidate/home', name: 'candidate_home')]
    public function home(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = (string) $session->get('user_id', '');
        $roles = (array) $session->get('user_roles', []);

        if ($userId === '') {
            return $this->redirectToRoute('app_login');
        }

        if (!in_array('ROLE_CANDIDATE', $roles, true)) {
            $this->addFlash('warning', 'This area is reserved for candidates.');

            return $this->redirectToRoute('front_home');
        }

        $candidate = $em->getRepository(Candidate::class)->find($userId);
        if (!$candidate instanceof Candidate) {
            $this->addFlash('error', 'Candidate profile not found.');

            return $this->redirectToRoute('front_home');
        }

        $candidateName = $this->resolveCandidateName($candidate, (string) $session->get('user_name', 'Candidate'));
        $now = new \DateTimeImmutable();

        $offers = $em->getRepository(Job_offer::class)
            ->createQueryBuilder('offer')
            ->where('LOWER(offer.status) = :status')
            ->andWhere('offer.deadline >= :now')
            ->setParameter('status', 'open')
            ->setParameter('now', $now)
            ->orderBy('offer.created_at', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        $offerCards = [];
        foreach ($offers as $offer) {
            if (!$offer instanceof Job_offer) {
                continue;
            }

            $offerCards[] = [
                'id' => (string) $offer->getId(),
                'title' => (string) $offer->getTitle(),
                'location' => (string) $offer->getLocation(),
                'contract_type' => (string) $offer->getContract_type(),
                'deadline' => $offer->getDeadline(),
            ];
        }

        $applications = $em->getRepository(Job_application::class)
            ->createQueryBuilder('application')
            ->where('application.candidate_id = :candidate')
            ->andWhere('application.is_archived = :isArchived')
            ->setParameter('candidate', $candidate)
            ->setParameter('isArchived', false)
            ->orderBy('application.applied_at', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $applicationCards = [];
        $applicationSummary = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];

        foreach ($applications as $application) {
            if (!$application instanceof Job_application) {
                continue;
            }

            [$statusLabel, $statusKey] = $this->mapCandidateApplicationStatus((string) $application->getCurrent_status());
            $applicationSummary[$statusKey]++;

            $offer = $application->getOffer_id();
            $applicationCards[] = [
                'id' => (string) $application->getId(),
                'offer_title' => $offer instanceof Job_offer ? (string) $offer->getTitle() : 'Unknown offer',
                'status_label' => $statusLabel,
                'status_key' => $statusKey,
                'applied_at' => $application->getApplied_at(),
            ];
        }

        $interviews = $em->getRepository(Interview::class)
            ->createQueryBuilder('interview')
            ->innerJoin('interview.application_id', 'application')
            ->where('application.candidate_id = :candidate')
            ->andWhere('interview.scheduled_at >= :now')
            ->setParameter('candidate', $candidate)
            ->setParameter('now', $now)
            ->orderBy('interview.scheduled_at', 'ASC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        $interviewCards = [];
        foreach ($interviews as $interview) {
            if (!$interview instanceof Interview) {
                continue;
            }

            $application = $interview->getApplication_id();
            $offer = $application instanceof Job_application ? $application->getOffer_id() : null;

            $interviewCards[] = [
                'title' => $offer instanceof Job_offer ? (string) $offer->getTitle() : 'Interview',
                'scheduled_at' => $interview->getScheduled_at(),
                'mode' => ucfirst((string) $interview->getMode()),
                'status' => ucfirst(strtolower((string) $interview->getStatus())),
            ];
        }

        return $this->render('front/candidate_home.html.twig', [
            'candidateName' => $candidateName,
            'introText' => 'Review open roles, track your applications, and stay prepared for upcoming interviews from one organized workspace.',
            'jobOffers' => $offerCards,
            'applications' => $applicationCards,
            'applicationSummary' => $applicationSummary,
            'interviews' => $interviewCards,
        ]);
    }

    private function resolveCandidateName(Candidate $candidate, string $fallback): string
    {
        $firstName = trim((string) $candidate->getFirstName());
        $lastName = trim((string) $candidate->getLastName());
        $fullName = trim($firstName . ' ' . $lastName);

        if ($fullName !== '') {
            return $fullName;
        }

        if ($firstName !== '') {
            return $firstName;
        }

        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : 'Candidate';
    }

    private function mapCandidateApplicationStatus(string $status): array
    {
        $normalized = strtoupper(trim($status));

        if (in_array($normalized, ['REJECTED', 'DECLINED'], true)) {
            return ['Rejected', 'rejected'];
        }

        if (in_array($normalized, ['HIRED', 'ACCEPTED', 'SHORTLISTED'], true)) {
            return ['Accepted', 'accepted'];
        }

        return ['Pending', 'pending'];
    }

    // ====================== SKILLS LIST + ADD ======================
    #[Route('/candidate/skills', name: 'app_candidate_skills', methods: ['GET', 'POST'])]
    public function index(Request $request, Candidate_skillRepository $skillRepo, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $skills = $skillRepo->findBy(['candidate' => $userId]);

        if ($request->isMethod('POST')) {
            $candidate = $em->getRepository(Candidate::class)->find($userId);

            $newSkill = new Candidate_skill();
            $newSkill->setSkillName($request->request->get('skill_name'));
            $newSkill->setLevel($request->request->get('level'));
            $newSkill->setCandidate($candidate);

            $em->persist($newSkill);
            $em->flush();

            $this->addFlash('success', 'Skill added successfully!');
            return $this->redirectToRoute('app_candidate_skills');
        }

        return $this->render('front/skills_page.html.twig', [
            'skills' => $skills
        ]);
    }

    // ====================== EDIT SKILL ======================
    #[Route('/candidate/skills/edit/{id}', name: 'app_candidate_skill_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Candidate_skill $skill, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        // Security: Make sure the skill belongs to the logged-in candidate
        if ($skill->getCandidate()->getId() !== $userId) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $skill->setSkillName($request->request->get('skill_name'));
            $skill->setLevel($request->request->get('level'));

            $em->flush();

            $this->addFlash('success', 'Skill updated successfully!');
            return $this->redirectToRoute('app_candidate_skills');
        }

        return $this->render('front/skills_edit.html.twig', [
            'skill' => $skill
        ]);
    }

// ====================== DELETE SKILL ======================
#[Route('/candidate/skills/delete/{id}', name: 'app_candidate_skill_delete')]
public function delete(
    Request $request,                    // ← Add this
    Candidate_skill $skill, 
    EntityManagerInterface $em
): Response
{
    $session = $request->getSession();
    $userId = $session->get('user_id');

    if (!$userId || $skill->getCandidate()->getId() !== $userId) {
        throw $this->createAccessDeniedException();
    }

    $em->remove($skill);
    $em->flush();

    $this->addFlash('success', 'Skill deleted successfully!');
    return $this->redirectToRoute('app_candidate_skills');
}







#[Route('/api/skill-suggestions', name: 'api_skill_suggestions', methods: ['GET'])]
public function skillSuggestions(
    Request $request,
    CacheInterface $cache
): JsonResponse {

    $query = strtolower(trim($request->query->get('q')));

    if (!$query || strlen($query) < 2) {
        return new JsonResponse([]);
    }

    $skills = $cache->get(

        'skills_' . $query,

        function (ItemInterface $item) use ($query) {

        dump('API CALL for: ' . $query); // DEBUG

            $item->expiresAfter(3600); // cache 1 hour

            $client = HttpClient::create([
                'timeout' => 3, // prevent long waits
            ]);

            try {

                $response = $client->request(
                    'GET',
                    'https://ec.europa.eu/esco/api/search',
                    [
                        'query' => [
                            'text' => $query,
                            'type' => 'skill',
                            'limit' => 8
                        ]
                    ]
                );

                $data = $response->toArray();

                $skills = [];

                if (isset($data['_embedded']['results'])) {
                    foreach ($data['_embedded']['results'] as $item) {
                        $skills[] = $item['title'];
                    }
                }

                return $skills;

            } catch (\Exception $e) {

                return [];

            }

        }

    );

    return new JsonResponse($skills);
}
}