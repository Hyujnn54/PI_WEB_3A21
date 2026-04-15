<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruiter;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminFrontController extends AbstractController
{
    #[Route('/admin/front-home', name: 'admin_front_home')]
    public function home(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = (string) $session->get('user_id', '');
        $roles = (array) $session->get('user_roles', []);

        if ($userId === '') {
            return $this->redirectToRoute('app_login');
        }

        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $this->addFlash('warning', 'This area is reserved for admins.');

            return $this->redirectToRoute('front_home');
        }

        $adminName = trim((string) $session->get('user_name', 'Admin'));
        if ($adminName === '') {
            $adminName = 'Admin';
        }

        $platformOverview = [
            'users' => (int) $em->getRepository(Users::class)->count([]),
            'job_offers' => (int) $em->getRepository(Job_offer::class)->count([]),
            'applications' => (int) $em->getRepository(Job_application::class)->count([]),
        ];

        $userOverview = [
            'candidates' => (int) $em->getRepository(Candidate::class)->count([]),
            'recruiters' => (int) $em->getRepository(Recruiter::class)->count([]),
        ];

        return $this->render('front/admin_home.html.twig', [
            'adminName' => $adminName,
            'platformOverview' => $platformOverview,
            'userOverview' => $userOverview,
            'roleDescription' => 'Oversee platform health, supervise activity, and access management areas for users, offers, and applications.',
        ]);
    }
}
