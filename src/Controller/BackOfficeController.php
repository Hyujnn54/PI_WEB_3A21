<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BackOfficeController extends AbstractController
{
    #[Route('/admin', name: 'back_dashboard')]
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'authUser' => ['role' => 'admin'],
            'kpis' => [
                ['label' => 'Total Users', 'value' => '1,378', 'icon' => 'ti ti-users'],
                ['label' => 'Open Offers', 'value' => '32', 'icon' => 'ti ti-briefcase-2'],
                ['label' => 'Applications', 'value' => '3,580', 'icon' => 'ti ti-file-check'],
                ['label' => 'Interviews', 'value' => '482', 'icon' => 'ti ti-message-2'],
            ],
        ]);
    }

    #[Route('/admin/add-user', name: 'app_admin_add_user')]
    public function addUser(): Response
    {
        return $this->render('admin/add_user.html.twig', [
            'authUser' => ['role' => 'admin'],
        ]);
    }

    #[Route('/admin/job-offers', name: 'app_admin_job_offers')]
    public function jobOffers(Connection $connection): Response
    {
        $offers = [];

        try {
            $offers = $connection->fetchAllAssociative(
                'SELECT id, recruiter_id, title, location, contract_type, status, created_at, deadline FROM job_offer ORDER BY created_at DESC'
            );
        } catch (\Throwable $exception) {
            // Keep admin page available even if table/query is unavailable.
        }

        return $this->render('admin/job_offers.html.twig', [
            'authUser' => ['role' => 'admin'],
            'offers' => $offers,
        ]);
    }
}
