<?php

namespace App\Controller\Management\JobOffer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/offermanagement')]
class JobOfferManagementController extends AbstractController
{
    #[Route('/admin/management/job-offers', name: 'management_job_offers')]
    public function index(): Response
    {
        return $this->render('management/job_offer/index.html.twig', [
            'module' => 'Job Offer Management',
            'description' => 'Manage offers, quality checks, and publication flow.',
            'authUser' => ['role' => 'admin'],
        ]);
    }
}
