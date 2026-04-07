<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/offermanagement')]
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
        $expiredOffers = [];
        $now = new \DateTimeImmutable();
        $offerStats = null;

        try {
            $this->closeExpiredOffers($connection, $now);
            $offers = $this->fetchAdminOffers($connection);
            $expiredOffers = $this->extractExpiredOffers($offers, $now);

            $offerStats = $this->buildOfferStats($offers);
        } catch (\Throwable $exception) {
            // Keep admin page available even if table/query is unavailable.
        }

        return $this->render('admin/job_offers.html.twig', [
            'authUser' => ['role' => 'admin'],
            'offers' => $offers,
            'expiredOffers' => $expiredOffers,
            'offerStats' => $offerStats,
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

    private function closeExpiredOffers(Connection $connection, \DateTimeImmutable $now): void
    {
        $connection->executeStatement(
            'UPDATE job_offer SET status = :closed_status WHERE deadline IS NOT NULL AND deadline < :now AND status <> :closed_status',
            [
                'closed_status' => 'closed',
                'now' => $now->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAdminOffers(Connection $connection): array
    {
        return $connection->fetchAllAssociative(
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
    }

    /**
     * @param array<int, array<string, mixed>> $offers
     * @return array<int, array<string, mixed>>
     */
    private function extractExpiredOffers(array $offers, \DateTimeImmutable $now): array
    {
        $expiredOffers = [];

        foreach ($offers as $offer) {
            if (empty($offer['deadline'])) {
                continue;
            }

            try {
                $deadlineAt = new \DateTimeImmutable((string) $offer['deadline']);
                if ($deadlineAt < $now) {
                    $expiredOffers[] = $offer;
                }
            } catch (\Throwable $exception) {
                // Ignore invalid dates in legacy rows.
            }
        }

        return $expiredOffers;
    }

    private function buildOfferStats(array $offers): array
    {
        $totalPublished = count($offers);
        $totalClosed = 0;
        $totalOpen = 0;
        $cityStats = [];
        $contractStats = [];

        foreach ($offers as $offer) {
            $city = trim((string) ($offer['location'] ?? 'Unknown'));
            if ($city === '') {
                $city = 'Unknown';
            }

            $contractType = trim((string) ($offer['contract_type'] ?? 'Unknown'));
            if ($contractType === '') {
                $contractType = 'Unknown';
            }

            $status = strtolower(trim((string) ($offer['status'] ?? 'open')));
            $isClosed = $status === 'closed';
            $isOpen = $status === 'open';

            if ($isClosed) {
                $totalClosed += 1;
            }
            if ($isOpen) {
                $totalOpen += 1;
            }

            if (!isset($cityStats[$city])) {
                $cityStats[$city] = ['city' => $city, 'total' => 0, 'open' => 0, 'closed' => 0];
            }
            $cityStats[$city]['total'] += 1;
            if ($isOpen) {
                $cityStats[$city]['open'] += 1;
            }
            if ($isClosed) {
                $cityStats[$city]['closed'] += 1;
            }

            if (!isset($contractStats[$contractType])) {
                $contractStats[$contractType] = ['contract_type' => $contractType, 'total' => 0, 'open' => 0, 'closed' => 0];
            }
            $contractStats[$contractType]['total'] += 1;
            if ($isOpen) {
                $contractStats[$contractType]['open'] += 1;
            }
            if ($isClosed) {
                $contractStats[$contractType]['closed'] += 1;
            }
        }

        $closedPercentage = $totalPublished > 0 ? round(($totalClosed / $totalPublished) * 100, 2) : 0.0;
        $openPercentage = $totalPublished > 0 ? round(($totalOpen / $totalPublished) * 100, 2) : 0.0;

        $cityStatsList = array_values($cityStats);
        foreach ($cityStatsList as &$row) {
            $row['open_rate'] = $row['total'] > 0 ? round(($row['open'] / $row['total']) * 100, 2) : 0.0;
            $row['closed_rate'] = $row['total'] > 0 ? round(($row['closed'] / $row['total']) * 100, 2) : 0.0;
        }

        $contractStatsList = array_values($contractStats);
        foreach ($contractStatsList as &$row) {
            $row['percentage'] = $totalPublished > 0 ? round(($row['total'] / $totalPublished) * 100, 2) : 0.0;
        }

        usort($cityStatsList, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        usort($contractStatsList, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'total_published' => $totalPublished,
            'total_closed' => $totalClosed,
            'total_open' => $totalOpen,
            'closed_percentage' => $closedPercentage,
            'open_percentage' => $openPercentage,
            'city_stats' => $cityStatsList,
            'contract_stats' => $contractStatsList,
        ];
    }
}
