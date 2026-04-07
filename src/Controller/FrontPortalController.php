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
    public function jobApplications(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => 'Application #1021 | Under Review', 'title' => 'Offer: Frontend Engineer', 'text' => 'Your profile passed initial screening and is awaiting recruiter feedback.'],
            ['meta' => 'Application #1022 | Interview Scheduled', 'title' => 'Offer: Symfony Backend Developer', 'text' => 'Technical interview is planned and pending confirmation details.'],
            ['meta' => 'Application #1023 | Accepted', 'title' => 'Offer: QA Engineer', 'text' => 'Your application has been approved and onboarding steps are prepared.'],
        ];

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
