<?php

namespace App\Controller\Management\JobApplication;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobApplicationManagementController extends AbstractController
{
    #[Route('/admin/job-applications', name: 'management_job_applications')]
    public function index(): Response
    {
        return $this->render('management/job_application/index.html.twig', [
            'module' => 'Job Application Management',
            'description' => 'Manage candidate applications and status transitions.',
        ]);
    }
}
