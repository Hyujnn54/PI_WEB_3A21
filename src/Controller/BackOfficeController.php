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

class BackOfficeController extends AbstractController
{
    #[Route('/admin', name: 'back_dashboard')]
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

        return $this->render('back/index.html.twig', [
            'stats' => $stats,
        ]);
    }
}
