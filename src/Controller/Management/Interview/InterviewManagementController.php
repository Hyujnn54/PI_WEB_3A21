<?php

namespace App\Controller\Management\Interview;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InterviewManagementController extends AbstractController
{
    #[Route('/admin/interviews', name: 'management_interviews')]
    public function index(Request $request): Response
    {
        $authUser = $request->getSession()->get('auth_user');
        if (!$authUser) {
            return $this->redirectToRoute('app_login');
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            return $this->redirectToRoute('front_home');
        }

        return $this->render('management/interview/index.html.twig', [
            'module' => 'Interview Management',
            'description' => 'Manage interview planning, feedback, and outcomes.',
            'authUser' => $authUser,
        ]);
    }
}
