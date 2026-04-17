<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Job_offer_warning;
use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/offermanagement')]
class BackOfficeController extends AbstractController
{
    #[Route('/admin', name: 'back_dashboard')]
    #[Route('/admin', name: 'app_admin')]
    public function index(UsersRepository $userRepo, EntityManagerInterface $entityManager): Response
    {
        $allUsers = $userRepo->findAll();
        $allOffers = $entityManager->getRepository(Job_offer::class)->findAll();
        $allApplications = $entityManager->getRepository(Job_application::class)->findAll();
        $allInterviews = $entityManager->getRepository(Interview::class)->findAll();

        $admins = 0;
        $candidates = 0;
        $recruiters = 0;

        foreach ($allUsers as $user) {
            $roles = $user->getRoles();

            if (in_array('ROLE_ADMIN', $roles, true)) {
                $admins += 1;
            }

            if (in_array('ROLE_CANDIDATE', $roles, true)) {
                $candidates += 1;
            }

            if (in_array('ROLE_RECRUITER', $roles, true)) {
                $recruiters += 1;
            }
        }

        $offersActive = 0;
        $offersInactive = 0;
        $now = new \DateTimeImmutable();
        foreach ($allOffers as $offer) {
            if (!$offer instanceof Job_offer) {
                continue;
            }

            $status = strtolower(trim((string) $offer->getStatus()));
            $deadline = $offer->getDeadline();
            $isExpired = $deadline instanceof \DateTimeInterface && $deadline < $now;
            $isActive = $status === 'open' && !$isExpired;

            if ($isActive) {
                $offersActive++;
            } else {
                $offersInactive++;
            }
        }

        $recentUsers = $entityManager->getRepository(Users::class)->findBy([], ['createdAt' => 'DESC'], 5);
        $recentOffers = $entityManager->getRepository(Job_offer::class)->findBy([], ['created_at' => 'DESC'], 5);
        $recentApplications = $entityManager->getRepository(Job_application::class)->findBy([], ['applied_at' => 'DESC'], 5);

        $recentActivity = [];

        foreach ($recentUsers as $user) {
            if (!$user instanceof Users) {
                continue;
            }

            $recentActivity[] = [
                'type' => 'user',
                'icon' => 'ti ti-user-plus',
                'label' => 'User created',
                'description' => trim((string) $user->getFirstName() . ' ' . (string) $user->getLastName()) !== ''
                    ? trim((string) $user->getFirstName() . ' ' . (string) $user->getLastName()) . ' joined the platform.'
                    : (string) $user->getEmail(),
                'created_at' => $user->getCreatedAt(),
            ];
        }

        foreach ($recentOffers as $offer) {
            if (!$offer instanceof Job_offer) {
                continue;
            }

            $recentActivity[] = [
                'type' => 'offer',
                'icon' => 'ti ti-briefcase-2',
                'label' => 'Job posted',
                'description' => (string) $offer->getTitle(),
                'created_at' => $offer->getCreated_at(),
            ];
        }

        foreach ($recentApplications as $application) {
            if (!$application instanceof Job_application) {
                continue;
            }

            $offer = $application->getOffer_id();
            $recentActivity[] = [
                'type' => 'application',
                'icon' => 'ti ti-file-check',
                'label' => 'Application submitted',
                'description' => $offer instanceof Job_offer ? (string) $offer->getTitle() : 'Job application submitted',
                'created_at' => $application->getApplied_at(),
            ];
        }

        usort($recentActivity, static function (array $a, array $b): int {
            $aTime = $a['created_at'] instanceof \DateTimeInterface ? $a['created_at']->getTimestamp() : 0;
            $bTime = $b['created_at'] instanceof \DateTimeInterface ? $b['created_at']->getTimestamp() : 0;

            return $bTime <=> $aTime;
        });

        $recentActivity = array_slice($recentActivity, 0, 8);

        $kpis = [
            ['label' => 'Total Users', 'value' => (string) count($allUsers), 'icon' => 'ti ti-users', 'tone' => 'primary'],
            ['label' => 'Total Job Offers', 'value' => (string) count($allOffers), 'icon' => 'ti ti-briefcase-2', 'tone' => 'warning'],
            ['label' => 'Total Applications', 'value' => (string) count($allApplications), 'icon' => 'ti ti-file-check', 'tone' => 'azure'],
            ['label' => 'Total Interviews', 'value' => (string) count($allInterviews), 'icon' => 'ti ti-message-2', 'tone' => 'success'],
        ];

        return $this->render('admin/index.html.twig', [
            'authUser' => ['role' => 'admin'],
            'kpis' => $kpis,
            'stats' => [
                'admins' => $admins,
                'candidates' => $candidates,
                'recruiters' => $recruiters,
                'interviews' => count($allInterviews),
            ],
            'usersPreview' => $recentUsers,
            'recentActivity' => $recentActivity,
            'offersOverview' => [
                'active' => $offersActive,
                'inactive' => $offersInactive,
            ],
            'systemControls' => [
                ['label' => 'Role & Permission Matrix', 'enabled' => true],
                ['label' => 'Candidate Self-Registration', 'enabled' => true],
                ['label' => 'Recruiter Offer Publishing', 'enabled' => true],
                ['label' => 'Automated Notifications', 'enabled' => false],
            ],
        ]);
    }

    #[Route('/admin/profile', name: 'app_admin_profile')]
    public function profile(Request $request, UsersRepository $userRepo): Response
    {
        $userId = (string) $request->getSession()->get('user_id', '');
        if ($userId === '') {
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepo->find($userId);
        if (!$user instanceof Users) {
            $this->addFlash('error', 'Profile not found.');

            return $this->redirectToRoute('app_admin');
        }

        $roles = $user->getRoles();
        $roleLabel = 'Candidate';
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $roleLabel = 'Administrator';
        } elseif (in_array('ROLE_RECRUITER', $roles, true)) {
            $roleLabel = 'Recruiter';
        }

        return $this->render('admin/profile.html.twig', [
            'authUser' => ['role' => 'admin'],
            'user' => $user,
            'roleLabel' => $roleLabel,
        ]);
    }

    #[Route('/admin/users', name: 'app_admin_users')]
    public function listUsers(UsersRepository $userRepo, Request $request): Response
    {
        $searchTerm = trim((string) $request->query->get('search', ''));
        $roleFilter = $request->query->get('role');

        if ($searchTerm !== '' || $roleFilter) {
            $users = $userRepo->findBySearchAndRole($searchTerm, $roleFilter);
        } else {
            $users = $userRepo->findAll();
        }

        $allUsers = $userRepo->findAll();
        $totalCount = count($allUsers);

        if ($request->query->get('ajax')) {
            return $this->render('admin/_user_table_rows.html.twig', [
                'users' => $users,
            ]);
        }

        return $this->render('admin/user_list.html.twig', [
            'authUser' => ['role' => 'admin'],
            'users' => $users,
            'searchTerm' => $searchTerm,
            'currentRole' => $roleFilter,
            'totalCount' => $totalCount,
        ]);
    }

    #[Route('/admin/add-user', name: 'app_admin_add_user', methods: ['GET', 'POST'])]
    public function addUser(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $user = new Admin();

            $user->setFirstName((string) $request->request->get('first_name'));
            $user->setLastName((string) $request->request->get('last_name'));
            $user->setEmail((string) $request->request->get('email'));
            $user->setPhone((string) $request->request->get('phone'));

            if (method_exists($user, 'setAssignedArea')) {
                $user->setAssignedArea('General Management');
            }

            $plainPassword = (string) $request->request->get('password');
            $user->setPassword($hasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_ADMIN']);
            $user->setIsActive(true);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Admin created successfully!');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/add_user.html.twig', [
            'authUser' => ['role' => 'admin'],
        ]);
    }

    #[Route('/admin/user/delete/{id}', name: 'app_admin_delete_user', methods: ['POST'])]
    public function deleteUser(int $id, UsersRepository $userRepo, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepo->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_admin_users');
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/admin/user/edit/{id}', name: 'app_admin_edit_user', methods: ['GET', 'POST'])]
    public function editUser(int $id, UsersRepository $userRepo, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepo->find($id);

        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($request->isMethod('POST')) {
            $user->setFirstName((string) $request->request->get('first_name'));
            $user->setLastName((string) $request->request->get('last_name'));
            $user->setEmail((string) $request->request->get('email'));

            $entityManager->flush();

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/edit_user.html.twig', [
            'authUser' => ['role' => 'admin'],
            'user' => $user,
        ]);
    }

    #[Route('/admin/stats', name: 'app_admin_stats')]
    public function userStats(UsersRepository $userRepo): Response
    {
        $allUsers = $userRepo->findAll();
        $admins = 0;
        $candidates = 0;
        $recruiters = 0;

        foreach ($allUsers as $user) {
            $roles = $user->getRoles();

            if (in_array('ROLE_ADMIN', $roles, true)) {
                $admins += 1;
            }
            if (in_array('ROLE_CANDIDATE', $roles, true)) {
                $candidates += 1;
            }
            if (in_array('ROLE_RECRUITER', $roles, true)) {
                $recruiters += 1;
            }
        }

        return $this->render('admin/stats.html.twig', [
            'authUser' => ['role' => 'admin'],
            'totalUsers' => count($allUsers),
            'admins' => $admins,
            'candidates' => $candidates,
            'recruiters' => $recruiters,
            'chartData' => [$admins, $recruiters, $candidates],
        ]);
    }

    #[Route('/admin/job-offers', name: 'app_admin_job_offers')]
    public function jobOffers(Connection $connection): Response
    {
        $offers = [];
        $expiredOffers = [];
        $now = new \DateTimeImmutable();

        try {
            try {
                $this->closeExpiredOffers($connection, $now);
            } catch (\Throwable) {
                // Keep read-only view available even if auto-close update fails.
            }

            $offers = $this->fetchAdminOffers($connection);
            $expiredOffers = $this->extractExpiredOffers($offers, $now);
        } catch (\Throwable $exception) {
            // Keep admin page available if any read query fails.
            $this->addFlash('error', 'Unable to load complete job offer data right now.');
        }

        return $this->render('admin/job_offers.html.twig', [
            'authUser' => ['role' => 'admin'],
            'offers' => $offers,
            'expiredOffers' => $expiredOffers,
        ]);
    }

    #[Route('/admin/job-offers/statistics', name: 'app_admin_job_offers_statistics')]
    public function jobOffersStatistics(Connection $connection): Response
    {
        $offers = [];
        $offerStats = $this->buildOfferStats([]);
        $now = new \DateTimeImmutable();

        try {
            try {
                $this->closeExpiredOffers($connection, $now);
            } catch (\Throwable) {
                // Keep read-only statistics available even if auto-close update fails.
            }

            $offers = $this->fetchAdminOffers($connection);
            $offerStats = $this->buildOfferStats($offers);
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Unable to load job offer statistics right now.');
        }

        return $this->render('admin/job_offer_statistics.html.twig', [
            'authUser' => ['role' => 'admin'],
            'offerStats' => $offerStats,
        ]);
    }

    #[Route('/admin/job-offers/{id}/warning', name: 'app_admin_job_offer_warning', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function sendJobOfferWarning(string $id, Request $request, Connection $connection): Response
    {
        $currentAdminId = (string) $request->getSession()->get('user_id', '');
        if ($currentAdminId === '') {
            $this->addFlash('error', 'You must be logged in as admin to send warnings.');
            return $this->redirectToRoute('app_login');
        }

        $validation = Job_offer_warning::validateWarningInput(
            (string) $request->request->get('warning_type', ''),
            (string) $request->request->get('warning_text', '')
        );
        if (($validation['ok'] ?? false) !== true) {
            $this->addFlash('warning_modal_error', (string) ($validation['error'] ?? 'Invalid warning input.'));
            $this->addFlash('warning_modal_mode', 'warn');
            $this->addFlash('warning_modal_type', trim((string) $request->request->get('warning_type', '')));
            $this->addFlash('warning_modal_text', trim((string) $request->request->get('warning_text', '')));
            return $this->redirectToRoute('app_admin_job_offers');
        }

        $warningType = (string) $validation['warningType'];
        $warningText = (string) $validation['warningText'];
        $reason = sprintf('[%s] %s', $warningType, $warningText);

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
                // Re-check inside transaction to avoid duplicate active warnings on concurrent requests.
                $activeWarningInTx = $connection->fetchOne(
                    'SELECT id FROM job_offer_warning WHERE job_offer_id = :job_offer_id AND status = :status LIMIT 1',
                    ['job_offer_id' => (string) $offer['id'], 'status' => 'SENT']
                );

                if ($activeWarningInTx) {
                    $connection->rollBack();
                    $this->addFlash('warning', 'This offer already has an active warning. Resolve it before sending a new one.');
                    return $this->redirectToRoute('app_admin_job_offers');
                }

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
                    'admin_id' => $currentAdminId,
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

    #[Route('/admin/job-offers/{id}/reject-changes', name: 'app_admin_reject_job_offer_changes', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function rejectJobOfferWarning(string $id, Request $request, Connection $connection): Response
    {
        $validation = Job_offer_warning::validateWarningInput(
            (string) $request->request->get('warning_type', ''),
            (string) $request->request->get('warning_text', '')
        );
        if (($validation['ok'] ?? false) !== true) {
            $this->addFlash('warning_modal_error', (string) ($validation['error'] ?? 'Invalid warning input.'));
            $this->addFlash('warning_modal_mode', 'reject');
            $this->addFlash('warning_modal_type', trim((string) $request->request->get('warning_type', '')));
            $this->addFlash('warning_modal_text', trim((string) $request->request->get('warning_text', '')));
            return $this->redirectToRoute('app_admin_job_offers');
        }

        $warningType = (string) $validation['warningType'];
        $warningText = (string) $validation['warningText'];
        $reason = sprintf('[%s] %s', $warningType, $warningText);

        try {
            $warning = $connection->fetchAssociative(
                'SELECT id FROM job_offer_warning WHERE job_offer_id = :job_offer_id AND status = :status LIMIT 1',
                ['job_offer_id' => $id, 'status' => 'RESOLVED']
            );

            if (!$warning) {
                $this->addFlash('error', 'No resolved warning found for this offer.');
                return $this->redirectToRoute('app_admin_job_offers');
            }

            $connection->update('job_offer_warning', [
                'status' => 'SENT',
                'reason' => $reason,
                'message' => $reason,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => (string) $warning['id'],
            ]);

            $this->addFlash('warning', 'Changes rejected. Recruiter must resolve the warning again or delete the offer.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Unable to reject changes for this offer.');
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

    #[Route('/admin/job-offers/{id}/details', name: 'app_admin_job_offer_details', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function jobOfferDetails(string $id, Connection $connection): JsonResponse
    {
        try {
            $offer = $connection->fetchAssociative(
                'SELECT id, recruiter_id, title, description, location, contract_type, status, created_at, deadline
                 FROM job_offer
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $id]
            );

            if (!$offer) {
                return new JsonResponse(['ok' => false, 'error' => 'Offer not found.'], 404);
            }

            $skills = $connection->fetchAllAssociative(
                'SELECT skill_name, level_required FROM offer_skill WHERE offer_id = :offer_id ORDER BY id ASC',
                ['offer_id' => $id]
            );

            $warningSql = <<<'SQL'
SELECT status, reason, created_at
FROM job_offer_warning
WHERE job_offer_id = :job_offer_id AND status IN ('SENT', 'RESOLVED')
ORDER BY created_at DESC
LIMIT 1
SQL;

            $warning = $connection->fetchAssociative($warningSql, ['job_offer_id' => $id]);

            return new JsonResponse([
                'ok' => true,
                'offer' => $offer,
                'skills' => $skills,
                'warning' => $warning ?: null,
            ]);
        } catch (\Throwable) {
            return new JsonResponse(['ok' => false, 'error' => 'Unable to load offer details.'], 500);
        }
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
        $sql = <<<'SQL'
SELECT jo.id, jo.recruiter_id, jo.title, jo.location, jo.contract_type, jo.status, jo.created_at, jo.deadline,
             COALESCE(jw.status, NULL) AS warning_status,
             jw.reason AS warning_reason
FROM job_offer jo
LEFT JOIN job_offer_warning jw
    ON jw.job_offer_id = jo.id
 AND jw.status IN ('SENT', 'RESOLVED')
 AND jw.created_at = (
         SELECT MAX(w2.created_at)
         FROM job_offer_warning w2
         WHERE w2.job_offer_id = jo.id
             AND w2.status IN ('SENT', 'RESOLVED')
 )
ORDER BY jo.created_at DESC
SQL;

        try {
            return $connection->fetchAllAssociative($sql);
        } catch (\Throwable) {
            $fallbackSql = <<<'SQL'
SELECT jo.id, jo.recruiter_id, jo.title, jo.location, jo.contract_type, jo.status, jo.created_at, jo.deadline,
       NULL AS warning_status,
    NULL AS warning_reason
FROM job_offer jo
ORDER BY jo.created_at DESC
SQL;

            return $connection->fetchAllAssociative($fallbackSql);
        }
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

    #[Route('/recruiter/create-event', name: 'recruiter_create_event', methods: ['GET', 'POST'])]
    public function createEvent(Request $request, EntityManagerInterface $entityManager): Response
    {
        $currentRecruiter = $this->resolveCurrentRecruiter($request, $entityManager);
        if (!$currentRecruiter instanceof Recruiter) {
            $this->addFlash('error', 'No recruiter account is linked to your current session.');
            return $this->redirectToRoute('front_events', ['role' => 'recruiter']);
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $eventType = trim((string) $request->request->get('event_type', ''));
            $location = trim((string) $request->request->get('location', ''));
            $eventDate = (string) $request->request->get('event_date', '');
            $capacity = (string) $request->request->get('capacity', '');
            $meetLink = trim((string) $request->request->get('meet_link', ''));

            if ($title === '') {
                $errors['title'] = 'Event title is required.';
            } elseif (strlen($title) < 3) {
                $errors['title'] = 'Event title must be at least 3 characters.';
            } elseif (strlen($title) > 255) {
                $errors['title'] = 'Event title cannot exceed 255 characters.';
            }

            if ($description === '') {
                $errors['description'] = 'Description is required.';
            } elseif (strlen($description) < 10) {
                $errors['description'] = 'Description must be at least 10 characters.';
            }

            if ($eventType === '') {
                $errors['event_type'] = 'Event type is required.';
            } elseif (!in_array($eventType, ['Workshop', 'Hiring Day', 'Webinar'], true)) {
                $errors['event_type'] = 'Invalid event type selected.';
            }

            if ($location === '') {
                $errors['location'] = 'Location is required.';
            } elseif (strlen($location) < 2) {
                $errors['location'] = 'Location must be at least 2 characters.';
            }

            if ($eventDate === '') {
                $errors['event_date'] = 'Event date is required.';
            } else {
                try {
                    $date = new \DateTime($eventDate);
                    $now = new \DateTime();
                    if ($date <= $now) {
                        $errors['event_date'] = 'Event date must be in the future.';
                    }
                } catch (\Exception) {
                    $errors['event_date'] = 'Invalid date format.';
                }
            }

            if ($capacity === '') {
                $errors['capacity'] = 'Capacity is required.';
            } else {
                $capacityInt = (int) $capacity;
                if ($capacityInt < 1) {
                    $errors['capacity'] = 'Capacity must be at least 1.';
                } elseif ($capacityInt > 1000) {
                    $errors['capacity'] = 'Capacity cannot exceed 1000.';
                }
            }

            if ($meetLink !== '' && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
                $errors['meet_link'] = 'Please enter a valid URL.';
            }

            if ($errors === []) {
                $event = new Recruitment_event();
                $event->setRecruiter_id($currentRecruiter);
                $event->setTitle($title);
                $event->setDescription($description);
                $event->setEvent_type($eventType);
                $event->setLocation($location);
                $event->setEvent_date(new \DateTime($eventDate));
                $event->setCapacity((int) $capacity);
                $event->setMeet_link($meetLink);
                $event->setCreated_at(new \DateTime());

                $entityManager->persist($event);
                $entityManager->flush();

                $this->addFlash('success', 'Event created successfully!');
                return $this->redirectToRoute('front_events');
            }
        }

        return $this->render('back/create_event.html.twig', [
            'authUser' => ['role' => 'recruiter'],
            'errors' => $errors,
            'isEdit' => false,
            'formData' => [
                'title' => '',
                'description' => '',
                'event_type' => '',
                'location' => '',
                'event_date' => '',
                'capacity' => '50',
                'meet_link' => '',
            ],
        ]);
    }

    #[Route('/recruiter/delete-event/{id}', name: 'recruiter_delete_event', methods: ['POST'])]
    public function deleteEvent(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $currentRecruiter = $this->resolveCurrentRecruiter($request, $entityManager);
        if (!$currentRecruiter instanceof Recruiter) {
            $this->addFlash('error', 'No recruiter account is linked to your current session.');
            return $this->redirectToRoute('front_events', ['role' => 'recruiter']);
        }

        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        if ((string) $event->getRecruiter_id()->getId() !== (string) $currentRecruiter->getId()) {
            $this->addFlash('warning', 'You can only delete events created by your account.');
            return $this->redirectToRoute('front_events', ['role' => 'recruiter']);
        }

        $entityManager->remove($event);
        $entityManager->flush();

        $this->addFlash('success', 'Event deleted successfully!');
        return $this->redirectToRoute('front_events');
    }

    #[Route('/recruiter/update-event/{id}', name: 'recruiter_update_event', methods: ['GET', 'POST'])]
    public function updateEvent(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $currentRecruiter = $this->resolveCurrentRecruiter($request, $entityManager);
        if (!$currentRecruiter instanceof Recruiter) {
            $this->addFlash('error', 'No recruiter account is linked to your current session.');
            return $this->redirectToRoute('front_events', ['role' => 'recruiter']);
        }

        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        if ((string) $event->getRecruiter_id()->getId() !== (string) $currentRecruiter->getId()) {
            $this->addFlash('warning', 'You can only update events created by your account.');
            return $this->redirectToRoute('front_events', ['role' => 'recruiter']);
        }

        if ($request->isMethod('GET')) {
            return $this->render('back/create_event.html.twig', [
                'authUser' => ['role' => 'recruiter'],
                'errors' => [],
                'isEdit' => true,
                'eventId' => $event->getId(),
                'formData' => [
                    'title' => (string) $event->getTitle(),
                    'description' => (string) $event->getDescription(),
                    'event_type' => (string) $event->getEvent_type(),
                    'location' => (string) $event->getLocation(),
                    'event_date' => $event->getEvent_date()->format('Y-m-d\TH:i'),
                    'capacity' => (string) $event->getCapacity(),
                    'meet_link' => (string) $event->getMeet_link(),
                ],
            ]);
        }

        $errors = [];
        $title = trim((string) $request->request->get('title', ''));
        $description = trim((string) $request->request->get('description', ''));
        $eventType = trim((string) $request->request->get('event_type', ''));
        $location = trim((string) $request->request->get('location', ''));
        $eventDate = (string) $request->request->get('event_date', '');
        $capacity = (string) $request->request->get('capacity', '');
        $meetLink = trim((string) $request->request->get('meet_link', ''));

        if ($title === '') {
            $errors['title'] = 'Event title is required.';
        } elseif (strlen($title) < 3) {
            $errors['title'] = 'Event title must be at least 3 characters.';
        } elseif (strlen($title) > 255) {
            $errors['title'] = 'Event title cannot exceed 255 characters.';
        }

        if ($description === '') {
            $errors['description'] = 'Description is required.';
        } elseif (strlen($description) < 10) {
            $errors['description'] = 'Description must be at least 10 characters.';
        }

        if ($eventType === '') {
            $errors['event_type'] = 'Event type is required.';
        } elseif (!in_array($eventType, ['Workshop', 'Hiring Day', 'Webinar'], true)) {
            $errors['event_type'] = 'Invalid event type selected.';
        }

        if ($location === '') {
            $errors['location'] = 'Location is required.';
        } elseif (strlen($location) < 2) {
            $errors['location'] = 'Location must be at least 2 characters.';
        }

        if ($eventDate === '') {
            $errors['event_date'] = 'Event date is required.';
        } else {
            try {
                $date = new \DateTime($eventDate);
                $now = new \DateTime();
                if ($date <= $now) {
                    $errors['event_date'] = 'Event date must be in the future.';
                }
            } catch (\Exception) {
                $errors['event_date'] = 'Invalid date format.';
            }
        }

        if ($capacity === '') {
            $errors['capacity'] = 'Capacity is required.';
        } else {
            $capacityInt = (int) $capacity;
            if ($capacityInt < 1) {
                $errors['capacity'] = 'Capacity must be at least 1.';
            } elseif ($capacityInt > 1000) {
                $errors['capacity'] = 'Capacity cannot exceed 1000.';
            }
        }

        if ($meetLink !== '' && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
            $errors['meet_link'] = 'Please enter a valid URL.';
        }

        if ($errors !== []) {
            return $this->render('back/create_event.html.twig', [
                'authUser' => ['role' => 'recruiter'],
                'errors' => $errors,
                'isEdit' => true,
                'eventId' => $event->getId(),
                'formData' => [
                    'title' => $title,
                    'description' => $description,
                    'event_type' => $eventType,
                    'location' => $location,
                    'event_date' => $eventDate,
                    'capacity' => $capacity,
                    'meet_link' => $meetLink,
                ],
            ]);
        }

        $event->setTitle($title);
        $event->setDescription($description);
        $event->setEvent_type($eventType);
        $event->setLocation($location);
        $event->setEvent_date(new \DateTime($eventDate));
        $event->setCapacity((int) $capacity);
        $event->setMeet_link($meetLink);

        $entityManager->flush();

        $this->addFlash('success', 'Event updated successfully!');
        return $this->redirectToRoute('front_events');
    }

    private function resolveCurrentRecruiter(Request $request, EntityManagerInterface $entityManager): ?Recruiter
    {
        $userId = (string) $request->getSession()->get('user_id', '');
        if ($userId === '') {
            return null;
        }

        $recruiterById = $entityManager->getRepository(Recruiter::class)->find($userId);
        if ($recruiterById instanceof Recruiter) {
            return $recruiterById;
        }

        try {
            $legacyRecruiterId = $entityManager->getConnection()->fetchOne(
                'SELECT id FROM recruiter WHERE user_id = :user_id LIMIT 1',
                ['user_id' => $userId]
            );
            if ($legacyRecruiterId !== false && $legacyRecruiterId !== null && (string) $legacyRecruiterId !== '') {
                $legacyRecruiter = $entityManager->getRepository(Recruiter::class)->find((string) $legacyRecruiterId);
                if ($legacyRecruiter instanceof Recruiter) {
                    return $legacyRecruiter;
                }
            }
        } catch (\Throwable) {
            // Keep backward compatibility when legacy column is unavailable.
        }

        return null;
    }
}
