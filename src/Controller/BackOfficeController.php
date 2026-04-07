<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BackOfficeController extends AbstractController
{
    private const STATIC_ADMIN_ID = '1';

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
                'SELECT jo.id, jo.recruiter_id, jo.title, jo.location, jo.contract_type, jo.status, jo.created_at, jo.deadline,
                        COALESCE(jw.status, NULL) AS warning_status,
                        jw.reason AS warning_reason,
                        jw.edited_at AS warning_edited_at
                 FROM job_offer jo
                 LEFT JOIN (
                     SELECT w1.job_offer_id, w1.status, w1.reason, w1.edited_at, w1.created_at
                     FROM job_offer_warning w1
                     WHERE w1.status IN ("SENT", "RESOLVED")
                     ORDER BY w1.created_at DESC
                 ) jw ON jw.job_offer_id = jo.id
                 ORDER BY jo.created_at DESC'
            );
        } catch (\Throwable $exception) {
            // Keep admin page available even if table/query is unavailable.
        }

        return $this->render('admin/job_offers.html.twig', [
            'authUser' => ['role' => 'admin'],
            'offers' => $offers,
        ]);
    }

    #[Route('/admin/job-offers/{id}/warning', name: 'app_admin_job_offer_warning', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function sendJobOfferWarning(string $id, Request $request, Connection $connection): Response
    {
        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Warning reason is required.');
            return $this->redirectToRoute('app_admin_job_offers');
        }

        try {
            $offer = $connection->fetchAssociative(
                'SELECT id, recruiter_id FROM job_offer WHERE id = :id LIMIT 1',
                ['id' => $id]
            );

            if (!$offer) {
                $this->addFlash('error', 'Job offer not found.');
                return $this->redirectToRoute('app_admin_job_offers');
            }

            // Check if there's an active (SENT) warning
            $activeWarning = $connection->fetchOne(
                'SELECT id FROM job_offer_warning WHERE job_offer_id = :job_offer_id AND status = :status LIMIT 1',
                ['job_offer_id' => (string) $offer['id'], 'status' => 'SENT']
            );

            if ($activeWarning) {
                $this->addFlash('warning', 'This offer already has an active warning. Wait for recruiter to edit or delete the warning first.');
                return $this->redirectToRoute('app_admin_job_offers');
            }

            $connection->beginTransaction();

            try {
                // If there's a RESOLVED warning (recruiter edited), delete it and create a new warning
                $connection->delete('job_offer_warning', [
                    'job_offer_id' => (string) $offer['id'],
                    'status' => 'RESOLVED',
                ]);

                $now = new \DateTimeImmutable();
                $warningId = (string) ((int) round(microtime(true) * 1000) . random_int(100, 999));

                $connection->insert('job_offer_warning', [
                    'id' => $warningId,
                    'job_offer_id' => (string) $offer['id'],
                    'recruiter_id' => (string) $offer['recruiter_id'],
                    'admin_id' => self::STATIC_ADMIN_ID,
                    'reason' => $reason,
                    'message' => $reason,
                    'status' => 'SENT',
                    'created_at' => $now->format('Y-m-d H:i:s'),
                    'seen_at' => $now->format('Y-m-d H:i:s'),
                    'resolved_at' => $now->format('Y-m-d H:i:s'),
                ]);

                $connection->commit();

                $this->addFlash('success', 'Warning sent to recruiter successfully.');
            } catch (\Throwable $exception) {
                $connection->rollBack();
                throw $exception;
            }
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Unable to send warning for this offer.');
        }

        return $this->redirectToRoute('app_admin_job_offers');
    }

    #[Route('/admin/job-offers/{id}/accept-changes', name: 'app_admin_accept_job_offer_changes', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function acceptJobOfferWarning(string $id, Connection $connection): Response
    {
        try {
            $warning = $connection->fetchAssociative(
                'SELECT id, job_offer_id FROM job_offer_warning WHERE job_offer_id = :job_offer_id AND status = :status LIMIT 1',
                ['job_offer_id' => $id, 'status' => 'RESOLVED']
            );

            if (!$warning) {
                $this->addFlash('error', 'No pending review found for this offer.');
                return $this->redirectToRoute('app_admin_job_offers');
            }

            $connection->update('job_offer_warning', [
                'status' => 'DISMISSED',
            ], [
                'id' => (string) $warning['id'],
            ]);

            $this->addFlash('success', 'Changes accepted. Warning cleared for this offer.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Unable to accept changes for this offer.');
        }

        return $this->redirectToRoute('app_admin_job_offers');
    }
}
