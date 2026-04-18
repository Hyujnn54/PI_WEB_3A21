<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruiter;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminFrontController extends AbstractController
{
    #[Route('/admin/front-home', name: 'admin_front_home')]
    public function home(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('warning', 'This area is reserved for admins.');

            return $this->redirectToRoute('front_home');
        }

        $adminName = trim((string) $user->getFirstName());
        if ($adminName === '') {
            $adminName = 'Admin';
        }

        $platformOverview = [
            'users' => 0,
            'job_offers' => 0,
            'applications' => 0,
        ];

        $userOverview = [
            'candidates' => 0,
            'recruiters' => 0,
        ];

        try {
            $platformOverview = [
                'users' => (int) $em->getRepository(Users::class)->count([]),
                'job_offers' => (int) $em->getRepository(Job_offer::class)->count([]),
                'applications' => (int) $em->getRepository(Job_application::class)->count([]),
            ];

            $userOverview = [
                'candidates' => (int) $em->getRepository(Candidate::class)->count([]),
                'recruiters' => (int) $em->getRepository(Recruiter::class)->count([]),
            ];
        } catch (\Throwable) {
            $this->addFlash('error', 'Database connection was temporarily lost. Please refresh the page.');
        }

        return $this->render('front/admin_home.html.twig', [
            'adminName' => $adminName,
            'platformOverview' => $platformOverview,
            'userOverview' => $userOverview,
            'roleDescription' => 'Oversee platform health, supervise activity, and access management areas for users, offers, and applications.',
        ]);
    }
}
