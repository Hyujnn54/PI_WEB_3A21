<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Candidate;
use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontOfficeController extends AbstractController
{
    #[Route('/', name: 'front_home')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $stats = [
            'admins' => $doctrine->getRepository(Admin::class)->count([]),
            'candidates' => $doctrine->getRepository(Candidate::class)->count([]),
            'recruiters' => $doctrine->getRepository(Recruiter::class)->count([]),
            'jobOffers' => $doctrine->getRepository(Job_offer::class)->count([]),
            'applications' => $doctrine->getRepository(Job_application::class)->count([]),
            'events' => $doctrine->getRepository(Recruitment_event::class)->count([]),
            'interviews' => $doctrine->getRepository(Interview::class)->count([]),
        ];

        $latestOffersEntities = $doctrine->getRepository(Job_offer::class)->findBy([], ['id' => 'DESC'], 6);
        $latestOffers = [];

        foreach ($latestOffersEntities as $offer) {
            $qualityScore = 0;

            try {
                $qualityScore = $offer->getQuality_score();
            } catch (\Error) {
                $qualityScore = 0;
            }

            $latestOffers[] = [
                'id' => $offer->getId(),
                'title' => $offer->getTitle(),
                'description' => $offer->getDescription(),
                'location' => $offer->getLocation(),
                'contract_type' => $offer->getContract_type(),
                'status' => $offer->getStatus(),
                'quality_score' => $qualityScore,
            ];
        }

        return $this->render('front/index.html.twig', [
            'stats' => $stats,
            'latestOffers' => $latestOffers,
        ]);
    }
}
