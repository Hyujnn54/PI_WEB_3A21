<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruiter;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RecruiterController extends AbstractController
{
    #[Route('/recruiter/home', name: 'recruiter_home')]
    public function home(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_RECRUITER')) {
            $this->addFlash('warning', 'This area is reserved for recruiters.');

            return $this->redirectToRoute('front_home');
        }

        $session = $request->getSession();
        $userId = (string) $user->getId();
        $recruiter = $em->getRepository(Recruiter::class)->find($userId);
        if (!$recruiter instanceof Recruiter) {
            $this->addFlash('error', 'Recruiter profile not found.');

            return $this->redirectToRoute('front_home');
        }

        $now = new \DateTimeImmutable();
        $recruiterName = $this->resolveRecruiterName($recruiter, (string) $session->get('user_name', 'Recruiter'));
        $companyName = trim((string) ($recruiter->getCompanyName() ?? ''));

        $offers = $em->getRepository(Job_offer::class)
            ->createQueryBuilder('offer')
            ->where('offer.recruiter_id = :recruiter')
            ->setParameter('recruiter', $recruiter)
            ->orderBy('offer.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        $jobOffersSummary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
        ];

        foreach ($offers as $offer) {
            if (!$offer instanceof Job_offer) {
                continue;
            }

            $jobOffersSummary['total']++;

            $status = strtolower(trim((string) $offer->getStatus()));
            $deadline = $offer->getDeadline();
            $isExpired = $deadline instanceof \DateTimeInterface && $deadline < $now;
            $isActive = $status === 'open' && !$isExpired;

            if ($isActive) {
                $jobOffersSummary['active']++;
            } else {
                $jobOffersSummary['inactive']++;
            }
        }

        $applications = [];
        if (!empty($offers)) {
            $applications = $em->getRepository(Job_application::class)
                ->createQueryBuilder('application')
                ->where('application.offer_id IN (:offers)')
                ->andWhere('application.is_archived = :isArchived')
                ->setParameter('offers', $offers)
                ->setParameter('isArchived', false)
                ->orderBy('application.applied_at', 'DESC')
                ->setMaxResults(8)
                ->getQuery()
                ->getResult();
        }

        $applicationCards = [];
        $applicationCounters = [
            'pending' => 0,
            'reviewed' => 0,
            'accepted' => 0,
            'rejected' => 0,
        ];

        foreach ($applications as $application) {
            if (!$application instanceof Job_application) {
                continue;
            }

            [$statusLabel, $statusKey] = $this->mapRecruiterApplicationStatus((string) $application->getCurrent_status());
            $applicationCounters[$statusKey]++;

            $offer = $application->getOffer_id();
            $candidate = $application->getCandidate_id();

            $applicationCards[] = [
                'id' => (string) $application->getId(),
                'offer_title' => $offer instanceof Job_offer ? (string) $offer->getTitle() : 'Unknown offer',
                'candidate_name' => $candidate instanceof Candidate ? $this->resolveCandidateName($candidate) : 'Candidate',
                'applied_at' => $application->getApplied_at(),
                'status_label' => $statusLabel,
                'status_key' => $statusKey,
            ];
        }

        $interviews = $em->getRepository(Interview::class)
            ->createQueryBuilder('interview')
            ->where('interview.recruiter_id = :recruiter')
            ->andWhere('interview.scheduled_at >= :now')
            ->setParameter('recruiter', $recruiter)
            ->setParameter('now', $now)
            ->orderBy('interview.scheduled_at', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $interviewCards = [];
        foreach ($interviews as $interview) {
            if (!$interview instanceof Interview) {
                continue;
            }

            $application = $interview->getApplication_id();
            $offer = $application instanceof Job_application ? $application->getOffer_id() : null;
            $candidate = $application instanceof Job_application ? $application->getCandidate_id() : null;
            $location = trim((string) $interview->getLocation());
            $meetingLink = trim((string) $interview->getMeeting_link());

            $interviewCards[] = [
                'title' => $offer instanceof Job_offer ? (string) $offer->getTitle() : 'Interview',
                'candidate_name' => $candidate instanceof Candidate ? $this->resolveCandidateName($candidate) : 'Candidate',
                'scheduled_at' => $interview->getScheduled_at(),
                'mode' => ucfirst((string) $interview->getMode()),
                'status' => ucfirst(strtolower((string) $interview->getStatus())),
                'meeting_info' => $location !== '' ? $location : ($meetingLink !== '' ? $meetingLink : 'TBD'),
            ];
        }

        $activitySummary = [
            'offers_total' => $jobOffersSummary['total'],
            'applications_total' => count($applicationCards),
            'interviews_upcoming' => count($interviewCards),
        ];

        return $this->render('front/recruiter_home.html.twig', [
            'recruiterName' => $recruiterName,
            'companyName' => $companyName,
            'activitySummary' => $activitySummary,
            'jobOffersSummary' => $jobOffersSummary,
            'applications' => $applicationCards,
            'applicationCounters' => $applicationCounters,
            'interviews' => $interviewCards,
        ]);
    }

    private function resolveRecruiterName(Recruiter $recruiter, string $fallback): string
    {
        $firstName = trim((string) $recruiter->getFirstName());
        $lastName = trim((string) $recruiter->getLastName());
        $fullName = trim($firstName . ' ' . $lastName);

        if ($fullName !== '') {
            return $fullName;
        }

        if ($firstName !== '') {
            return $firstName;
        }

        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : 'Recruiter';
    }

    private function resolveCandidateName(Candidate $candidate): string
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

        return 'Candidate';
    }

    private function mapRecruiterApplicationStatus(string $status): array
    {
        $normalized = strtoupper(trim($status));

        if (in_array($normalized, ['REJECTED', 'DECLINED'], true)) {
            return ['Rejected', 'rejected'];
        }

        if (in_array($normalized, ['HIRED', 'ACCEPTED'], true)) {
            return ['Accepted', 'accepted'];
        }

        if (in_array($normalized, ['SHORTLISTED', 'INTERVIEW', 'IN_REVIEW', 'INTERVIEW_SCHEDULED', 'UNDER_REVIEW'], true)) {
            return ['Reviewed', 'reviewed'];
        }

        return ['Pending', 'pending'];
    }
}
