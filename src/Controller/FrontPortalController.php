<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontPortalController extends AbstractController
{
    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(Request $request, EntityManagerInterface $em): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        
        $jobOffers = $em->getRepository(Job_offer::class)->findAll();
        $candidateId = 3;
        $appliedOfferIds = [];

        $candidate = $em->getRepository(Candidate::class)->find($candidateId);
        if ($candidate) {
            $activeApplications = $em->getRepository(Job_application::class)->findBy([
                'candidate_id' => $candidate,
                'is_archived' => false,
            ]);

            foreach ($activeApplications as $activeApplication) {
                $offer = $activeApplication->getOffer_id();
                if ($offer) {
                    $appliedOfferIds[(string) $offer->getId()] = true;
                }
            }
        }
        
        $cards = [];
        foreach ($jobOffers as $offer) {
            $cards[] = [
                'id' => $offer->getId(),
                'meta' => $offer->getLocation() . ' | ' . $offer->getContract_type(),
                'title' => $offer->getTitle(),
                'text' => $offer->getDescription(),
                'already_applied' => isset($appliedOfferIds[(string) $offer->getId()]),
            ];
        }

        return $this->render('front/modules/job_offers.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/job-applications', name: 'front_job_applications')]
    public function jobApplications(Request $request, EntityManagerInterface $em): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $candidateId = 3;
        $cards = [];

        $candidate = $em->getRepository(Candidate::class)->find($candidateId);
        if ($candidate) {
            $applications = $em->getRepository(Job_application::class)->findBy(
                ['candidate_id' => $candidate],
                ['applied_at' => 'DESC']
            );

            foreach ($applications as $application) {
                $offer = $application->getOffer_id();
                $offerTitle = $offer ? $offer->getTitle() : 'Unknown Offer';
                $status = $application->getCurrent_status();
                $appliedAt = $application->getApplied_at();
                $canWithdraw = strtoupper(trim((string) $status)) === 'SUBMITTED';

                $cards[] = [
                    'id' => $application->getId(),
                    'meta' => $status,
                    'title' => 'Offer: ' . $offerTitle,
                    'text' => 'Applied on ' . $appliedAt->format('d M Y H:i') . ' | Phone: ' . $application->getPhone(),
                    'details_url' => $this->generateUrl('app_candidate_application_details', ['applicationId' => $application->getId()]),
                    'can_withdraw' => $canWithdraw,
                    'withdraw_url' => $canWithdraw
                        ? $this->generateUrl('app_candidate_application_withdraw', ['applicationId' => $application->getId()])
                        : '#',
                ];
            }
        }

        return $this->render('front/modules/job_applications.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events', name: 'front_events')]
    public function events(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => '12 Apr 2026 | Tunis', 'title' => 'Tech Hiring Day', 'text' => 'Meet recruiters and discover active engineering opportunities.'],
            ['meta' => '20 Apr 2026 | Sousse', 'title' => 'Career Talk', 'text' => 'Panel discussion with hiring managers and senior developers.'],
            ['meta' => '28 Apr 2026 | Remote', 'title' => 'Virtual Assessment Workshop', 'text' => 'Online guidance session for interview and coding assessments.'],
        ];

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/interviews', name: 'front_interviews')]
    public function interviews(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => '15 Apr 2026 | 10:00 | Scheduled', 'title' => 'Interview: Frontend Engineer', 'text' => 'Prepare portfolio walkthrough and component design discussion.'],
            ['meta' => '18 Apr 2026 | 14:30 | Pending Feedback', 'title' => 'Interview: Symfony Backend Developer', 'text' => 'Technical round completed, feedback consolidation in progress.'],
            ['meta' => '22 Apr 2026 | 09:30 | Completed', 'title' => 'Interview: QA Engineer', 'text' => 'Process completed, final decision and follow-up underway.'],
        ];

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }
}
