<?php

namespace App\Controller\Management\JobApplication;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobApplicationManagementController extends AbstractController
{
    #[Route('/admin/job-applications', name: 'management_job_applications')]
    public function index(Request $request): Response
    {
        $authUser = $request->getSession()->get('auth_user');
        if (!$authUser) {
            return $this->redirectToRoute('app_login');
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            return $this->redirectToRoute('front_home');
        }

        return $this->render('management/job_application/index.html.twig', [
            'module' => 'Job Application Management',
            'description' => 'Manage candidate applications and status transitions.',
            'authUser' => $authUser,
        ]);
    }
}
