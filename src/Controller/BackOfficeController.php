<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Job_offer_comment;
use App\Entity\Job_offer_warning;
use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use App\Entity\Users;
use App\Form\Filter\JobOfferFilterType;
use App\Repository\Job_offerRepository;
use App\Repository\UsersRepository;
use App\Service\CommentAnalyzerService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdaterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/offermanagement')]
class BackOfficeController extends AbstractController
{
    private const CONTRACT_TYPES = ['CDI', 'CDD', 'Internship', 'Freelance', 'Full-time', 'Part-time', 'Remote Contract'];
    private const JOB_STATUSES = ['open', 'paused', 'closed'];
    private const WARNING_TYPES = [
        'Policy violation',
        'Incorrect information',
        'Missing required details',
        'Deadline issue',
        'Description trompeuse',
        'Other',
    ];
    private const GROQ_WARNING_MODELS = ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'];

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
        $now = date_create();
        foreach ($allOffers as $offer) {
            $status = strtolower(trim((string) $offer->getStatus()));
            $deadline = $offer->getDeadline();
            $isExpired = is_object($deadline) && method_exists($deadline, 'getTimestamp') && $deadline < $now;
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
            $recentActivity[] = [
                'type' => 'offer',
                'icon' => 'ti ti-briefcase-2',
                'label' => 'Job posted',
                'description' => (string) $offer->getTitle(),
                'created_at' => $offer->getCreated_at(),
            ];
        }

        foreach ($recentApplications as $application) {
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
            $aTime = is_object($a['created_at'] ?? null) && method_exists($a['created_at'], 'getTimestamp') ? $a['created_at']->getTimestamp() : 0;
            $bTime = is_object($b['created_at'] ?? null) && method_exists($b['created_at'], 'getTimestamp') ? $b['created_at']->getTimestamp() : 0;

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
        $user = $this->getUser();
        $userId = $user instanceof Users ? (string) $user->getId() : '';
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

            $user->setAssignedArea('General Management');

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
        $totalUsers = $userRepo->count([]);
        $admins = $userRepo->countByRole('ROLE_ADMIN');
        $candidates = $userRepo->countByRole('ROLE_CANDIDATE');
        $recruiters = $userRepo->countByRole('ROLE_RECRUITER');

        return $this->render('admin/stats.html.twig', [
            'authUser' => ['role' => 'admin'],
            'totalUsers' => $totalUsers,
            'admins' => $admins,
            'candidates' => $candidates,
            'recruiters' => $recruiters,
            'chartData' => [$admins, $recruiters, $candidates],
        ]);
    }

    #[Route('/admin/job-offers', name: 'app_admin_job_offers')]
    public function jobOffers(
        Request $request,
        Connection $connection,
        Job_offerRepository $jobOfferRepository,
        FilterBuilderUpdaterInterface $filterBuilderUpdater
    ): Response
    {
        $filterForm = $this->createForm(JobOfferFilterType::class, null, [
            'method' => 'GET',
            'csrf_protection' => false,
            'contract_types' => self::CONTRACT_TYPES,
            'job_statuses' => self::JOB_STATUSES,
        ]);
        $filterForm->handleRequest($request);

        $offers = [];
        $expiredOffers = [];
        $now = date_create();

        try {
            try {
                $this->closeExpiredOffers($connection, $now);
            } catch (\Throwable) {
                // Keep read-only view available even if auto-close update fails.
            }

            $filterBuilder = $jobOfferRepository->createAdminOffersFilterQueryBuilder();
            if ($filterForm->isSubmitted() && $filterForm->isValid()) {
                $filterBuilderUpdater->addFilterConditions($filterForm, $filterBuilder);
            }

            $offers = $jobOfferRepository->getAdminOffersFromQueryBuilder($filterBuilder, 300);
            $expiredOffers = $this->extractExpiredOffers($offers, $now);
        } catch (\Throwable $exception) {
            // Keep admin page available if any read query fails.
            $this->addFlash('error', 'Unable to load complete job offer data right now.');
        }

        $filterData = (array) $filterForm->getData();
        $hasActiveFilters = trim((string) ($filterData['search'] ?? '')) !== ''
            || trim((string) ($filterData['contract_type'] ?? '')) !== ''
            || trim((string) ($filterData['status'] ?? '')) !== ''
            || trim((string) ($filterData['deadline'] ?? '')) !== '';

        return $this->render('admin/job_offers.html.twig', [
            'authUser' => ['role' => 'admin'],
            'offers' => $offers,
            'expiredOffers' => $expiredOffers,
            'filterForm' => $filterForm->createView(),
            'hasActiveFilters' => $hasActiveFilters,
            'resultCount' => count($offers),
        ]);
    }

    #[Route('/admin/job-offers/statistics', name: 'app_admin_job_offers_statistics')]
    public function jobOffersStatistics(Connection $connection, Job_offerRepository $jobOfferRepository): Response
    {
        $offerStats = [
            'total_published' => 0,
            'total_closed' => 0,
            'total_open' => 0,
            'closed_percentage' => 0,
            'open_percentage' => 0,
            'city_stats' => [],
            'contract_stats' => [],
        ];
        $now = new \DateTimeImmutable();

        try {
            try {
                $this->closeExpiredOffers($connection, $now);
            } catch (\Throwable) {
                // Keep read-only statistics available even if auto-close update fails.
            }

            $offerStats = $jobOfferRepository->buildAdminOfferStats(1000);
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Unable to load job offer statistics right now.');
        }

        return $this->render('admin/job_offer_statistics.html.twig', [
            'authUser' => ['role' => 'admin'],
            'offerStats' => $offerStats,
        ]);
    }

    #[Route('/admin/job-offers/statistics/export/pdf', name: 'app_admin_job_offers_statistics_export_pdf', methods: ['GET'])]
    public function exportJobOffersStatisticsPdf(Connection $connection, Job_offerRepository $jobOfferRepository, Pdf $pdf): Response
    {
        $offerStats = [
            'total_published' => 0,
            'total_closed' => 0,
            'total_open' => 0,
            'closed_percentage' => 0,
            'open_percentage' => 0,
            'city_stats' => [],
            'contract_stats' => [],
        ];
        $now = new \DateTimeImmutable();

        try {
            try {
                $this->closeExpiredOffers($connection, $now);
            } catch (\Throwable) {
                // Keep export available even if auto-close update fails.
            }

            $offerStats = $jobOfferRepository->buildAdminOfferStats(1000);
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to export admin statistics right now.');

            return $this->redirectToRoute('app_admin_job_offers_statistics');
        }

        $logoDataUri = null;
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $logoCandidates = [
            $projectDir . '/assets/team_upload/logo.png',
            $projectDir . '/assets/team_uploads/logo.png',
            $projectDir . '/public/uploads/applications/logo-69d55696892a5.png',
        ];
        foreach ($logoCandidates as $logoPath) {
            if (!is_file($logoPath)) {
                continue;
            }

            $logoBinary = @file_get_contents($logoPath);
            if ($logoBinary !== false) {
                $logoDataUri = 'data:image/png;base64,' . base64_encode($logoBinary);
                break;
            }
        }

        $html = $this->renderView('pdf/admin_job_offer_statistics.pdf.twig', [
            'offerStats' => $offerStats,
            'generatedAt' => new \DateTimeImmutable(),
            'logoDataUri' => $logoDataUri,
        ]);

        $content = $pdf->getOutputFromHtml($html, [
            'encoding' => 'utf-8',
            'enable-local-file-access' => true,
            'margin-top' => '12mm',
            'margin-bottom' => '12mm',
            'margin-left' => '10mm',
            'margin-right' => '10mm',
        ]);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="admin_offer_statistics_' . (new \DateTimeImmutable())->format('Ymd_His') . '.pdf"',
        ]);
    }

    #[Route('/admin/job-offers/{id}/warning', name: 'app_admin_job_offer_warning', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function sendJobOfferWarning(string $id, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        $currentAdminId = $user instanceof Users ? (string) $user->getId() : '';
        if ($currentAdminId === '') {
            $this->addFlash('error', 'You must be logged in as admin to send warnings.');
            return $this->redirectToRoute('app_login');
        }

        $validation = Job_offer_warning::validateWarningInput(
            (string) $request->request->get('warning_type', ''),
            (string) $request->request->get('warning_text', '')
        );
        if ($validation['ok'] !== true) {
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

                $now = date_create();
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

    #[Route('/admin/job-offers/warning/ai-generate', name: 'app_admin_job_offer_warning_ai_generate', methods: ['POST'])]
    public function generateWarningMessageWithAi(Request $request, Connection $connection, HttpClientInterface $httpClient): JsonResponse
    {
        $user = $this->getUser();
        $currentAdminId = $user instanceof Users ? (string) $user->getId() : '';
        if ($currentAdminId === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Admin session is required.'], 403);
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Only admins can generate warning messages.'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $offerId = trim((string) ($payload['offer_id'] ?? ''));
        $warningType = trim((string) ($payload['warning_type'] ?? ''));

        if ($offerId === '' || $warningType === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Offer ID and warning type are required.'], 400);
        }

        if (!in_array($warningType, self::WARNING_TYPES, true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Please select a valid warning type.'], 400);
        }

        $offer = $connection->fetchAssociative(
            'SELECT id, title, description, location, contract_type FROM job_offer WHERE id = :id LIMIT 1',
            ['id' => $offerId]
        );
        if (!$offer) {
            return new JsonResponse(['ok' => false, 'error' => 'Job offer not found.'], 404);
        }

        $skills = $connection->fetchAllAssociative(
            'SELECT skill_name, level_required FROM offer_skill WHERE offer_id = :offer_id ORDER BY id ASC',
            ['offer_id' => $offerId]
        );

        $skillsList = 'None';
        if (count($skills) > 0) {
            $parts = [];
            foreach ($skills as $skill) {
                $parts[] = sprintf('%s (%s)', (string) ($skill['skill_name'] ?? '-'), (string) ($skill['level_required'] ?? '-'));
            }
            $skillsList = implode(', ', $parts);
        }

        $prompt = "Tu es un assistant RH senior.\n"
            . "Rédige un message de warning professionnel, clair et actionnable pour un recruteur.\n"
            . "Le message doit rester respectueux mais ferme, expliquer le problème, demander des corrections précises,"
            . " et indiquer qu'une mise à jour est attendue rapidement.\n"
            . "Retourne uniquement le texte du message (sans markdown, sans puces).\n\n"
            . "Raison sélectionnée: {$warningType}\n"
            . "Titre offre: " . (string) ($offer['title'] ?? '') . "\n"
            . "Location: " . (string) ($offer['location'] ?? '') . "\n"
            . "Contract type: " . (string) ($offer['contract_type'] ?? '') . "\n"
            . "Description: " . (string) ($offer['description'] ?? '') . "\n"
            . "Skills: {$skillsList}\n";

        $apiKey = $this->resolveGroqApiKey();
        $warningMessage = '';
        $source = 'fallback';

        if ($apiKey !== '') {
            $aiResult = $this->requestGroqWarningMessage($httpClient, $apiKey, $prompt);
            if (($aiResult['ok'] ?? false) === true) {
                $warningMessage = $this->normalizeWarningMessage((string) ($aiResult['message'] ?? ''));
                $source = 'ai';
            }
        }

        if ($warningMessage === '') {
            $warningMessage = $this->normalizeWarningMessage(sprintf(
                'Following the review of your job offer "%s", we identified the following issue: %s. Please update the description and related fields so the content is accurate, complete, and aligned with platform standards. Submit the corrected version for admin review as soon as possible.',
                (string) ($offer['title'] ?? 'this position'),
                $warningType
            ));
            $source = 'fallback';
        }

        $validation = Job_offer_warning::validateWarningInput($warningType, $warningMessage);
        if ($validation['ok'] !== true) {
            $warningMessage = $this->normalizeWarningMessage('Please revise this job offer. The content currently does not meet platform quality and compliance standards. Update the listing with clear, accurate, and complete information, then resubmit for review.');
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $warningMessage,
            'source' => $source,
        ]);
    }

    #[Route('/admin/job-offers/{id}/reject-changes', name: 'app_admin_reject_job_offer_changes', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function rejectJobOfferWarning(string $id, Request $request, Connection $connection): Response
    {
        $validation = Job_offer_warning::validateWarningInput(
            (string) $request->request->get('warning_type', ''),
            (string) $request->request->get('warning_text', '')
        );
        if ($validation['ok'] !== true) {
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
                'created_at' => date_create()->format('Y-m-d H:i:s'),
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

    #[Route('/admin/job-offers/{id}/analyze', name: 'app_admin_job_offer_analyze', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function analyzeJobOffer(
        string $id,
        Connection $connection,
        CommentAnalyzerService $commentAnalyzerService,
        HttpClientInterface $httpClient
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof Users || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Only admins can analyze job offers.'], 403);
        }

        $offer = $connection->fetchAssociative(
            'SELECT id, recruiter_id, title, description, location, contract_type, status, deadline
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

        $skillsText = $this->formatOfferSkillsForPrompt($skills);
        $offerText = sprintf(
            "Title: %s\nContract: %s\nLocation: %s\nStatus: %s\nDeadline: %s\nDescription: %s\nSkills: %s",
            (string) ($offer['title'] ?? ''),
            (string) ($offer['contract_type'] ?? ''),
            (string) ($offer['location'] ?? ''),
            (string) ($offer['status'] ?? ''),
            (string) ($offer['deadline'] ?? ''),
            (string) ($offer['description'] ?? ''),
            $skillsText
        );

        $commentAnalysis = $commentAnalyzerService->analyze($offerText);
        $groqAnalysis = $this->analyzeOfferWithGroq($httpClient, $offer, $skillsText);
        $flagDecision = $this->decideOfferFlagState($commentAnalysis, $groqAnalysis);
        $analysisSummary = [
            'flagged' => $flagDecision['flagged'],
            'reason' => $flagDecision['reason'],
            'commentAnalyzer' => [
                'toxicityScore' => (float) ($commentAnalysis['toxicityScore'] ?? 0),
                'spamScore' => (float) ($commentAnalysis['spamScore'] ?? 0),
                'sentiment' => (string) ($commentAnalysis['sentiment'] ?? 'neutral'),
                'labels' => (array) ($commentAnalysis['labels'] ?? []),
            ],
            'groq' => [
                'source' => (string) ($groqAnalysis['source'] ?? 'unknown'),
                'riskLevel' => (string) ($groqAnalysis['riskLevel'] ?? 'unknown'),
                'summary' => (string) ($groqAnalysis['summary'] ?? ''),
                'issues' => (array) ($groqAnalysis['issues'] ?? []),
                'recommendations' => (array) ($groqAnalysis['recommendations'] ?? []),
            ],
        ];

        $encodedSummary = (string) json_encode($analysisSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $connection->update('job_offer', [
            'is_flagged' => $flagDecision['flagged'] ? 1 : 0,
            'flagged_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'quality_score' => $flagDecision['qualityScore'],
            'ai_suggestions' => mb_substr($encodedSummary, 0, 5000),
        ], [
            'id' => $id,
        ]);

        return new JsonResponse([
            'ok' => true,
            'commentAnalysis' => $commentAnalysis,
            'groqAnalysis' => $groqAnalysis,
            'flagDecision' => $flagDecision,
        ]);
    }

    #[Route('/admin/comment-analyzer', name: 'app_admin_comment_analyzer', methods: ['GET'])]
    public function commentAnalyzerDashboard(Request $request, Connection $connection): Response
    {
        $status = strtoupper(trim((string) $request->query->get('status', 'ALL')));
        $allowedStatuses = [
            'ALL',
            Job_offer_comment::STATUS_FLAGGED,
            Job_offer_comment::STATUS_APPROVED,
            Job_offer_comment::STATUS_REJECTED,
            Job_offer_comment::STATUS_WARNED,
        ];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'ALL';
        }

        $sql = <<<'SQL'
SELECT c.id, c.comment_text, c.toxicity_score, c.spam_score, c.sentiment, c.labels,
       c.moderation_status, c.visibility_status, c.created_at, c.moderated_at, c.moderator_action_note,
       j.id AS offer_id, j.title AS offer_title,
       u.first_name, u.last_name, u.email
FROM job_offer_comment c
LEFT JOIN job_offer j ON j.id = c.job_offer_id
LEFT JOIN users u ON u.id = c.candidate_id
SQL;

        $params = [];
        if ($status !== 'ALL') {
            $sql .= ' WHERE c.moderation_status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY c.created_at DESC LIMIT 300';

        $comments = [];
        $flaggedCount = 0;
        try {
            $rows = $connection->fetchAllAssociative($sql, $params);
            $comments = array_map(function (array $row): array {
                $labels = [];
                $decoded = json_decode((string) ($row['labels'] ?? '[]'), true);
                if (is_array($decoded)) {
                    foreach ($decoded as $label) {
                        $value = trim((string) $label);
                        if ($value !== '') {
                            $labels[] = $value;
                        }
                    }
                }

                $authorName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                if ($authorName === '') {
                    $authorName = (string) ($row['email'] ?? 'Candidate');
                }

                return [
                    'id' => (string) ($row['id'] ?? ''),
                    'offer_id' => (string) ($row['offer_id'] ?? ''),
                    'offer_title' => (string) ($row['offer_title'] ?? 'Unknown offer'),
                    'author' => $authorName,
                    'comment_text' => (string) ($row['comment_text'] ?? ''),
                    'toxicity_score' => round((float) ($row['toxicity_score'] ?? 0), 3),
                    'spam_score' => round((float) ($row['spam_score'] ?? 0), 3),
                    'sentiment' => (string) ($row['sentiment'] ?? 'neutral'),
                    'labels' => $labels,
                    'moderation_status' => (string) ($row['moderation_status'] ?? Job_offer_comment::STATUS_APPROVED),
                    'visibility_status' => (string) ($row['visibility_status'] ?? Job_offer_comment::VISIBILITY_VISIBLE),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'moderated_at' => (string) ($row['moderated_at'] ?? ''),
                    'moderator_action_note' => (string) ($row['moderator_action_note'] ?? ''),
                ];
            }, $rows);

            $flaggedCount = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM job_offer_comment WHERE moderation_status = :status',
                ['status' => Job_offer_comment::STATUS_FLAGGED]
            );
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to load comment analyzer data right now.');
        }

        return $this->render('admin/comment_analyzer.html.twig', [
            'authUser' => ['role' => 'admin'],
            'comments' => $comments,
            'selectedStatus' => $status,
            'allowedStatuses' => $allowedStatuses,
            'flaggedCount' => $flaggedCount,
        ]);
    }

    #[Route('/admin/comment-analyzer/analyze', name: 'app_admin_comment_analyzer_analyze', methods: ['POST'])]
    public function analyzeCommentPayload(Request $request, CommentAnalyzerService $commentAnalyzerService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $comment = trim((string) ($payload['comment'] ?? ''));
        $validation = Job_offer_comment::validateCommentText($comment);
        if (($validation['ok'] ?? false) !== true) {
            return new JsonResponse([
                'ok' => false,
                'error' => (string) ($validation['error'] ?? 'Invalid comment.'),
            ], 400);
        }

        return new JsonResponse([
            'ok' => true,
            'analysis' => $commentAnalyzerService->analyze((string) $validation['value']),
        ]);
    }

    #[Route('/admin/comment-analyzer/{id}/approve', name: 'app_admin_comment_analyzer_approve', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function approveComment(string $id, Request $request, Connection $connection): Response
    {
        $adminId = (string) $request->getSession()->get('user_id', '');
        $note = trim((string) $request->request->get('note', 'Approved after moderator review.'));

        $ok = $this->applyCommentModeration(
            $connection,
            $id,
            $adminId,
            Job_offer_comment::STATUS_APPROVED,
            Job_offer_comment::VISIBILITY_VISIBLE,
            $note
        );

        $this->addFlash($ok ? 'success' : 'error', $ok ? 'Comment approved.' : 'Unable to approve comment.');

        return $this->redirectToRoute('app_admin_comment_analyzer');
    }

    #[Route('/admin/comment-analyzer/{id}/reject', name: 'app_admin_comment_analyzer_reject', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function rejectComment(string $id, Request $request, Connection $connection): Response
    {
        $adminId = (string) $request->getSession()->get('user_id', '');
        $note = trim((string) $request->request->get('note', 'Rejected due to policy or quality issues.'));

        $ok = $this->applyCommentModeration(
            $connection,
            $id,
            $adminId,
            Job_offer_comment::STATUS_REJECTED,
            Job_offer_comment::VISIBILITY_HIDDEN,
            $note
        );

        $this->addFlash($ok ? 'warning' : 'error', $ok ? 'Comment rejected and hidden.' : 'Unable to reject comment.');

        return $this->redirectToRoute('app_admin_comment_analyzer');
    }

    #[Route('/admin/comment-analyzer/{id}/warn', name: 'app_admin_comment_analyzer_warn', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function warnCommentAuthor(string $id, Request $request, Connection $connection): Response
    {
        $adminId = (string) $request->getSession()->get('user_id', '');
        $note = trim((string) $request->request->get('warning_note', 'Please keep comments constructive and respectful.'));
        $hideComment = trim((string) $request->request->get('hide_comment', '0')) === '1';

        if (mb_strlen($note) > 500) {
            $note = trim(mb_substr($note, 0, 500));
        }

        $ok = $this->applyCommentModeration(
            $connection,
            $id,
            $adminId,
            Job_offer_comment::STATUS_WARNED,
            $hideComment ? Job_offer_comment::VISIBILITY_HIDDEN : Job_offer_comment::VISIBILITY_VISIBLE,
            $note
        );

        $this->addFlash($ok ? 'warning' : 'error', $ok ? 'User warning recorded for this comment.' : 'Unable to warn user for this comment.');

        return $this->redirectToRoute('app_admin_comment_analyzer');
    }

    private function closeExpiredOffers(Connection $connection, \DateTimeInterface $now): void
    {
        $connection->executeStatement(
            'UPDATE job_offer SET status = :closed_status WHERE deadline IS NOT NULL AND deadline < :now AND status <> :closed_status',
            [
                'closed_status' => 'closed',
                'now' => $now->format('Y-m-d H:i:s'),
            ]
        );
    }

    private function applyCommentModeration(
        Connection $connection,
        string $commentId,
        string $adminId,
        string $status,
        string $visibility,
        string $note
    ): bool {
        if ($commentId === '' || $adminId === '') {
            return false;
        }

        try {
            $affectedRows = $connection->update('job_offer_comment', [
                'moderation_status' => $status,
                'visibility_status' => $visibility,
                'moderated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'moderator_id' => $adminId,
                'moderator_action_note' => $note,
            ], [
                'id' => $commentId,
            ]);

            return $affectedRows > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $offers
     * @return array<int, array<string, mixed>>
     */
    private function extractExpiredOffers(array $offers, $now): array
    {
        $expiredOffers = [];

        foreach ($offers as $offer) {
            if (empty($offer['deadline'])) {
                continue;
            }

            try {
                $deadlineAt = date_create((string) $offer['deadline']);
                if ($deadlineAt < $now) {
                    $expiredOffers[] = $offer;
                }
            } catch (\Throwable $exception) {
                // Ignore invalid dates in legacy rows.
            }
        }

        return $expiredOffers;
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
        $formData = [
            'title' => '',
            'description' => '',
            'event_type' => '',
            'location' => '',
            'location_lat' => '',
            'location_lng' => '',
            'event_date' => '',
            'capacity' => '50',
            'meet_link' => '',
        ];

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $eventType = trim((string) $request->request->get('event_type', ''));
            $location = trim((string) $request->request->get('location', ''));
            $locationLat = trim((string) $request->request->get('location_lat', ''));
            $locationLng = trim((string) $request->request->get('location_lng', ''));
            $eventDate = (string) $request->request->get('event_date', '');
            $capacity = (string) $request->request->get('capacity', '');
            $meetLink = trim((string) $request->request->get('meet_link', ''));

            $formData = [
                'title' => $title,
                'description' => $description,
                'event_type' => $eventType,
                'location' => $location,
                'location_lat' => $locationLat,
                'location_lng' => $locationLng,
                'event_date' => $eventDate,
                'capacity' => $capacity === '' ? '50' : $capacity,
                'meet_link' => $meetLink,
            ];

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
                    $date = date_create($eventDate);
                    $now = date_create();
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
                $event->setEvent_date(date_create($eventDate));
                $event->setCapacity((int) $capacity);
                $event->setMeet_link($meetLink);
                $event->setCreated_at(date_create());

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
            'formData' => $formData,
        ]);
    }

    #[Route('/recruiter/events/ai-description', name: 'recruiter_generate_event_description', methods: ['POST'])]
    public function generateEventDescription(Request $request, EntityManagerInterface $entityManager, HttpClientInterface $httpClient): JsonResponse
    {
        $currentRecruiter = $this->resolveCurrentRecruiter($request, $entityManager);
        if (!$currentRecruiter instanceof Recruiter) {
            return new JsonResponse(['ok' => false, 'error' => 'Recruiter session is required.'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $eventType = trim((string) ($payload['event_type'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $eventDate = trim((string) ($payload['event_date'] ?? ''));
        $capacity = trim((string) ($payload['capacity'] ?? ''));

        if ($title === '' || $eventType === '' || $location === '' || $eventDate === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Title, event type, location, and event date are required.'], 400);
        }

        $apiUrl = trim((string) ($_ENV['AI_CHAT_COMPLETIONS_URL'] ?? $_SERVER['AI_CHAT_COMPLETIONS_URL'] ?? getenv('AI_CHAT_COMPLETIONS_URL') ?: ''));
        if ($apiUrl === '') {
            $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
        }

        $model = trim((string) ($_ENV['AI_MODEL'] ?? $_SERVER['AI_MODEL'] ?? getenv('AI_MODEL') ?: ''));
        if ($model === '') {
            $model = 'openrouter/auto';
        }

        $apiKey = $this->resolveEventDescriptionApiKey();
        $fallbackDescription = $this->buildEventDescriptionFallback($title, $eventType, $location, $eventDate, $capacity);

        if ($apiKey === '') {
            return new JsonResponse([
                'ok' => true,
                'description' => $fallbackDescription,
                'source' => 'fallback',
            ]);
        }

        $prompt = "Write one concise, attractive recruitment event description (2-4 sentences) for candidates.\n"
            . "Keep it professional, human, and action-oriented.\n"
            . "Do not use markdown.\n\n"
            . "Event title: {$title}\n"
            . "Event type: {$eventType}\n"
            . "Location: {$location}\n"
            . "Event date: {$eventDate}\n"
            . "Capacity: " . ($capacity !== '' ? $capacity : 'N/A') . "\n";

        try {
            $response = $httpClient->request('POST', $apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => $request->getSchemeAndHttpHost(),
                    'X-Title' => 'Talent Bridge Event Description',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a senior recruitment marketing copywriter.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 260,
                ],
                'timeout' => 25,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('AI provider returned an error status.');
            }

            $body = $response->toArray(false);
            $rawText = trim((string) ($body['choices'][0]['message']['content'] ?? ''));
            $description = $this->normalizeEventDescription($rawText);
            if ($description === '') {
                throw new \RuntimeException('AI provider returned an empty message.');
            }

            return new JsonResponse([
                'ok' => true,
                'description' => $description,
                'source' => 'ai',
            ]);
        } catch (\Throwable) {
            return new JsonResponse([
                'ok' => true,
                'description' => $fallbackDescription,
                'source' => 'fallback',
            ]);
        }
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
                    'location_lat' => '',
                    'location_lng' => '',
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
        $locationLat = trim((string) $request->request->get('location_lat', ''));
        $locationLng = trim((string) $request->request->get('location_lng', ''));
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
                $date = date_create($eventDate);
                $now = date_create();
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
                    'location_lat' => $locationLat,
                    'location_lng' => $locationLng,
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
        $event->setEvent_date(date_create($eventDate));
        $event->setCapacity((int) $capacity);
        $event->setMeet_link($meetLink);

        $entityManager->flush();

        $this->addFlash('success', 'Event updated successfully!');
        return $this->redirectToRoute('front_events');
    }

    private function resolveEventDescriptionApiKey(): string
    {
        $candidates = [
            $_ENV['AI_API_KEY'] ?? null,
            $_SERVER['AI_API_KEY'] ?? null,
            getenv('AI_API_KEY') ?: null,
            $_ENV['OPENROUTER_API_KEY'] ?? null,
            $_SERVER['OPENROUTER_API_KEY'] ?? null,
            getenv('OPENROUTER_API_KEY') ?: null,
            $_ENV['GROQ_API_KEY'] ?? null,
            $_SERVER['GROQ_API_KEY'] ?? null,
            getenv('GROQ_API_KEY') ?: null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function buildEventDescriptionFallback(string $title, string $eventType, string $location, string $eventDate, string $capacity): string
    {
        $safeTitle = $title !== '' ? $title : 'Recruitment Event';
        $safeType = $eventType !== '' ? $eventType : 'recruitment event';
        $safeLocation = $location !== '' ? $location : 'our venue';
        $safeCapacity = trim($capacity) !== '' ? trim($capacity) : 'limited';

        $dateLabel = '';
        try {
            $parsedDate = new \DateTimeImmutable($eventDate);
            $dateLabel = $parsedDate->format('F j, Y \\a\\t H:i');
        } catch (\Throwable) {
            $dateLabel = $eventDate;
        }

        $capacityPhrase = $safeCapacity === 'limited'
            ? 'Seats are limited, so early registration is recommended.'
            : 'Capacity is limited to ' . $safeCapacity . ' participants, so early registration is recommended.';

        return $this->normalizeEventDescription(
            $safeTitle . ' is an upcoming ' . $safeType . ' in ' . $safeLocation
            . ($dateLabel !== '' ? ' on ' . $dateLabel : '')
            . '. This event is designed to connect candidates with recruiters through practical insights and direct networking opportunities. '
            . $capacityPhrase
        );
    }

    private function normalizeEventDescription(string $raw): string
    {
        $text = trim($raw);
        if ($text === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) > 1200) {
            $text = trim(mb_substr($text, 0, 1200));
        }

        return $text;
    }

    private function resolveCurrentRecruiter(Request $request, EntityManagerInterface $entityManager): ?Recruiter
    {
        $user = $this->getUser();
        $userId = $user instanceof Users ? (string) $user->getId() : '';
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

    private function resolveGroqApiKey(): string
    {
        $candidates = [
            $_ENV['GROQ_API_KEY'] ?? null,
            $_SERVER['GROQ_API_KEY'] ?? null,
            getenv('GROQ_API_KEY') ?: null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function requestGroqWarningMessage(HttpClientInterface $httpClient, string $apiKey, string $prompt): array
    {
        foreach (self::GROQ_WARNING_MODELS as $model) {
            try {
                $response = $httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You write concise, professional HR compliance messages.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => 0.3,
                        'max_tokens' => 260,
                    ],
                    'timeout' => 25,
                ]);

                if ($response->getStatusCode() >= 400) {
                    continue;
                }

                $body = $response->toArray(false);
                $text = trim((string) ($body['choices'][0]['message']['content'] ?? ''));
                if ($text === '') {
                    continue;
                }

                return ['ok' => true, 'message' => $text, 'model' => $model];
            } catch (\Throwable) {
                continue;
            }
        }

        return ['ok' => false, 'message' => 'Groq generation unavailable'];
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return array<string, mixed>
     */
    private function analyzeOfferWithGroq(HttpClientInterface $httpClient, array $offer, string $skillsText): array
    {
        $apiKey = $this->resolveGroqApiKey();
        if ($apiKey === '') {
            return [
                'ok' => false,
                'source' => 'unconfigured',
                'summary' => 'Groq is not configured. Add GROQ_API_KEY to enable AI offer analysis.',
                'riskLevel' => 'unknown',
                'issues' => [],
                'recommendations' => [],
            ];
        }

        $prompt = "You are an HR compliance reviewer for a recruitment platform.\n"
            . "Analyze this job offer for quality, missing information, policy risks, misleading language, discrimination risk, and recruiter action items.\n"
            . "Return JSON only with keys: riskLevel (low|medium|high), summary, issues (array), recommendations (array).\n\n"
            . "Title: " . (string) ($offer['title'] ?? '') . "\n"
            . "Contract: " . (string) ($offer['contract_type'] ?? '') . "\n"
            . "Location: " . (string) ($offer['location'] ?? '') . "\n"
            . "Status: " . (string) ($offer['status'] ?? '') . "\n"
            . "Deadline: " . (string) ($offer['deadline'] ?? '') . "\n"
            . "Description: " . (string) ($offer['description'] ?? '') . "\n"
            . "Skills: {$skillsText}\n";

        foreach (self::GROQ_WARNING_MODELS as $model) {
            try {
                $response = $httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You return compact valid JSON for HR compliance analysis.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => 0.2,
                        'max_tokens' => 500,
                        'response_format' => ['type' => 'json_object'],
                    ],
                    'timeout' => 25,
                ]);

                if ($response->getStatusCode() >= 400) {
                    continue;
                }

                $body = $response->toArray(false);
                $text = trim((string) ($body['choices'][0]['message']['content'] ?? ''));
                $decoded = json_decode($text, true);
                if (!is_array($decoded)) {
                    continue;
                }

                return [
                    'ok' => true,
                    'source' => 'groq',
                    'model' => $model,
                    'riskLevel' => $this->normalizeGroqRiskLevel((string) ($decoded['riskLevel'] ?? 'medium')),
                    'summary' => $this->normalizeGroqText((string) ($decoded['summary'] ?? 'Analysis completed.')),
                    'issues' => $this->normalizeGroqList($decoded['issues'] ?? []),
                    'recommendations' => $this->normalizeGroqList($decoded['recommendations'] ?? []),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return [
            'ok' => false,
            'source' => 'fallback',
            'summary' => 'Groq analysis is unavailable right now. Use the analyzer scores below and review the offer manually.',
            'riskLevel' => 'unknown',
            'issues' => [],
            'recommendations' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $skills
     */
    private function formatOfferSkillsForPrompt(array $skills): string
    {
        if ($skills === []) {
            return 'None listed';
        }

        $parts = [];
        foreach ($skills as $skill) {
            $name = trim((string) ($skill['skill_name'] ?? ''));
            $level = trim((string) ($skill['level_required'] ?? ''));
            if ($name === '') {
                continue;
            }

            $parts[] = $level !== '' ? sprintf('%s (%s)', $name, $level) : $name;
        }

        return $parts === [] ? 'None listed' : implode(', ', $parts);
    }

    private function normalizeGroqRiskLevel(string $riskLevel): string
    {
        $value = strtolower(trim($riskLevel));

        return in_array($value, ['low', 'medium', 'high'], true) ? $value : 'medium';
    }

    private function normalizeGroqText(string $text): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_substr($value, 0, 500);
    }

    /**
     * @return string[]
     */
    private function normalizeGroqList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $value = $this->normalizeGroqText((string) $item);
            if ($value !== '') {
                $normalized[] = mb_substr($value, 0, 220);
            }

            if (count($normalized) >= 5) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $commentAnalysis
     * @param array<string, mixed> $groqAnalysis
     *
     * @return array{flagged: bool, reason: string, qualityScore: int}
     */
    private function decideOfferFlagState(array $commentAnalysis, array $groqAnalysis): array
    {
        $toxicityScore = (float) ($commentAnalysis['toxicityScore'] ?? 0);
        $spamScore = (float) ($commentAnalysis['spamScore'] ?? 0);
        $commentFlagged = ($commentAnalysis['flagged'] ?? false) === true;
        $groqRisk = strtolower(trim((string) ($groqAnalysis['riskLevel'] ?? 'unknown')));

        $flagged = $commentFlagged
            || $toxicityScore >= 0.75
            || $spamScore >= 0.72
            || $groqRisk === 'high'
            || ($groqRisk === 'medium' && ($toxicityScore >= 0.55 || $spamScore >= 0.55));

        if ($flagged) {
            $reasonParts = [];
            if ($commentFlagged || $toxicityScore >= 0.75 || $spamScore >= 0.72) {
                $reasonParts[] = 'Comment Analyzer detected elevated toxicity/spam risk';
            }

            if ($groqRisk === 'high') {
                $reasonParts[] = 'Groq classified the offer as high risk';
            } elseif ($groqRisk === 'medium') {
                $reasonParts[] = 'Groq classified the offer as medium risk with elevated analyzer scores';
            }

            return [
                'flagged' => true,
                'reason' => implode('; ', $reasonParts),
                'qualityScore' => $groqRisk === 'high' ? 35 : 55,
            ];
        }

        return [
            'flagged' => false,
            'reason' => 'Comment Analyzer and Groq did not detect enough risk to flag this offer.',
            'qualityScore' => $groqRisk === 'medium' ? 75 : 95,
        ];
    }

    private function normalizeWarningMessage(string $raw): string
    {
        $message = trim($raw);
        if ($message === '') {
            return '';
        }

        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        $message = preg_replace('/[^\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]/u', ' ', $message) ?? $message;
        $message = trim($message);

        if (mb_strlen($message) > 500) {
            $message = trim(mb_substr($message, 0, 500));
        }

        return $message;
    }
}
