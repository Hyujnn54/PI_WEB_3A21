<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Candidate_skill;
use App\Entity\Application_status_history;
use App\Entity\Event_registration;
use App\Entity\Interview;
use App\Entity\Interview_feedback;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Job_offer_comment;
use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use App\Entity\Users;
use App\Form\Filter\JobOfferFilterType;
use App\Repository\Job_applicationRepository;
use App\Repository\Job_offerRepository;
use App\Form\ProfileType;
use App\Repository\InterviewRepository;
use App\Repository\UsersRepository;
use App\Service\Interview\InterviewCalendarService;
use App\Service\Interview\JitsiMeetingLinkGenerator;
use App\Service\JobApplication\ApplicationAiRankingService;
use App\Service\CandidateOfferMatchingService;
use App\Service\CommentAnalyzerService;
use App\Service\GeolocationService;
use App\Service\JobOfferLocationGeocoder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Knp\Snappy\Pdf;
use Psr\Log\LoggerInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdaterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[Route('/offermanagement')]
class FrontPortalController extends AbstractController
{
    private const MAX_FUTURE_DAYS = 90;
    private const EDIT_LOCK_HOURS = 2;
    private const REVIEW_COMMENT_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,1000}$/u';
    private const CONTRACT_TYPES = ['CDI', 'CDD', 'Internship', 'Freelance', 'Full-time', 'Part-time', 'Remote Contract'];
    private const SKILL_LEVELS = ['beginner', 'intermediate', 'advanced'];
    private const JOB_STATUSES = ['open', 'paused', 'closed'];
    private const GROQ_JOB_OFFER_MODELS = ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'];
    private const EVENT_LIST_LIMIT = 50;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly ApplicationAiRankingService $applicationAiRankingService,
        private readonly CandidateOfferMatchingService $candidateOfferMatchingService,
        private readonly CommentAnalyzerService $commentAnalyzerService,
        private readonly JobOfferLocationGeocoder $jobOfferLocationGeocoder,
        private readonly GeolocationService $geolocationService,
        private readonly InterviewCalendarService $interviewCalendarService,
    )
    {
    }

    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(
        Request $request,
        Connection $connection,
        Job_offerRepository $jobOfferRepository,
        FilterBuilderUpdaterInterface $filterBuilderUpdater
    ): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        $candidate = null;
        $filterForm = $this->createForm(JobOfferFilterType::class, null, [
            'method' => 'GET',
            'csrf_protection' => false,
            'contract_types' => self::CONTRACT_TYPES,
            'job_statuses' => self::JOB_STATUSES,
        ]);
        $filterForm->handleRequest($request);

        $warnings = [];
        $warningStatuses = [];
        $expiredOffers = [];
        $now = new \DateTimeImmutable();
        $cards = [];

        $appliedOfferIds = [];
        if ($role === 'candidate') {
            $candidate = $this->resolveCurrentCandidate($request);
            if ($candidate instanceof Candidate) {
                $activeApplications = $this->doctrine->getRepository(Job_application::class)->findBy([
                    'candidate_id' => $candidate,
                    'is_archived' => false,
                ]);

                foreach ($activeApplications as $activeApplication) {
                    $offer = $activeApplication->getOffer_id();
                    $appliedOfferIds[(string) $offer->getId()] = true;
                }
            }
        }

        

        try {
            if ($role === 'recruiter') {
                $connection->executeStatement(
                    'UPDATE job_offer SET status = :closed_status WHERE recruiter_id = :recruiter_id AND deadline IS NOT NULL AND deadline < :now AND status <> :closed_status',
                    [
                        'recruiter_id' => $currentRecruiterId,
                        'closed_status' => 'closed',
                        'now' => $now->format('Y-m-d H:i:s'),
                    ]
                );
            }

            $filterBuilder = $jobOfferRepository->createPortalOffersFilterQueryBuilder($role, $currentRecruiterId);
            if ($filterForm->isSubmitted() && $filterForm->isValid()) {
                $filterBuilderUpdater->addFilterConditions($filterForm, $filterBuilder);
            }

            $rows = $jobOfferRepository->getPortalOffersFromQueryBuilder($filterBuilder, 25);

            $candidateMatchData = [];
            if ($role === 'candidate' && $candidate instanceof Candidate) {
                $candidateMatchData = $this->candidateOfferMatchingService->buildCandidateOfferMatchData((string) $candidate->getId(), $rows);
            }

            $dbCards = array_map(function (array $row) use ($connection, $now, $appliedOfferIds, $currentRecruiterId, $candidateMatchData): array {
                $formattedDeadline = '';
                $isExpired = false;
                try {
                    $deadlineAt = date_create((string) ($row['deadline'] ?? ''));
                    if ($deadlineAt instanceof \DateTimeInterface) {
                        $formattedDeadline = $deadlineAt->format('Y-m-d');
                        $isExpired = $deadlineAt < $now;
                    }
                } catch (\Throwable $exception) {
                    $formattedDeadline = '';
                    $isExpired = false;
                }

                $skills = $connection->fetchAllAssociative(
                    'SELECT skill_name, level_required FROM offer_skill WHERE offer_id = :offer_id ORDER BY id ASC',
                    ['offer_id' => (string) $row['id']]
                );

                $matchData = $candidateMatchData[(string) $row['id']] ?? null;
                $matchScore = is_array($matchData) ? (int) ($matchData['score'] ?? 0) : null;
                $matchLabel = is_array($matchData) ? (string) ($matchData['label'] ?? '') : '';
                $matchDetails = is_array($matchData) ? (array) ($matchData['details'] ?? []) : [];
                $matchSummary = is_array($matchData)
                    ? sprintf(
                        '%d requises, %d alignées, %d partielles, %d manquantes',
                        (int) ($matchDetails['required_skill_count'] ?? 0),
                        (int) ($matchDetails['matching_skill_count'] ?? 0),
                        (int) ($matchDetails['partial_match_count'] ?? 0),
                        (int) ($matchDetails['missing_skill_count'] ?? 0)
                    )
                    : '';

                $detailExtra = [
                    'Type: ' . (string) $row['contract_type'],
                    'Location: ' . (string) $row['location'],
                    'Status: ' . ucfirst((string) $row['status']),
                    'Deadline: ' . ($formattedDeadline !== '' ? $formattedDeadline : 'N/A'),
                ];

                if (count($skills) > 0) {
                    foreach ($skills as $skill) {
                        $detailExtra[] = sprintf(
                            'Skill: %s (%s)',
                            (string) $skill['skill_name'],
                            ucfirst((string) $skill['level_required'])
                        );
                    }
                } else {
                    $detailExtra[] = 'Skills: Not specified';
                }

                if (is_array($matchData)) {
                    $detailExtra[] = 'Match score: ' . $matchScore . '% (' . $matchLabel . ')';
                    $detailExtra[] = 'Matching skills: ' . (count((array) ($matchData['matching_skills'] ?? [])) > 0 ? implode(', ', (array) $matchData['matching_skills']) : 'None');
                    $detailExtra[] = 'Missing skills: ' . (count((array) ($matchData['missing_skills'] ?? [])) > 0 ? implode(', ', (array) $matchData['missing_skills']) : 'None');
                    $detailExtra[] = 'Explanation: ' . (string) ($matchData['explanation'] ?? 'No explanation available.');
                }

                return [
                    'id' => (string) $row['id'],
                    'meta' => sprintf('%s | %s | %s', (string) $row['location'], (string) $row['contract_type'], ucfirst((string) $row['status'])),
                    'title' => (string) $row['title'],
                    'text' => (string) $row['description'],
                    'already_applied' => isset($appliedOfferIds[(string) $row['id']]),
                    'can_apply' => strtolower((string) $row['status']) === 'open' && !$isExpired && !isset($appliedOfferIds[(string) $row['id']]),
                    'can_delete' => (string) $row['recruiter_id'] === $currentRecruiterId,
                    'detail_extra' => $detailExtra,
                    'recruiter_id' => (string) $row['recruiter_id'],
                    'location' => (string) $row['location'],
                    'contract_type' => (string) $row['contract_type'],
                    'status' => (string) $row['status'],
                    'deadline' => $formattedDeadline,
                    'is_expired' => $isExpired,
                    'match_score' => $matchScore,
                    'match_label' => $matchLabel,
                    'match_summary' => $matchSummary,
                    'match_explanation' => is_array($matchData) ? (string) ($matchData['explanation'] ?? '') : '',
                    'match_matching_skills' => is_array($matchData) ? implode(', ', (array) ($matchData['matching_skills'] ?? [])) : '',
                    'match_missing_skills' => is_array($matchData) ? implode(', ', (array) ($matchData['missing_skills'] ?? [])) : '',
                    'match_details' => is_array($matchData) ? (array) ($matchData['details'] ?? []) : [],
                ];
            }, $rows);

            // --- Geolocation: geocode the candidate's city in-memory (no DB write) ---
            $candidateLat = null;
            $candidateLng = null;
            if ($role === 'candidate' && $candidate instanceof Candidate) {
                $candidateCity = $candidate->getLocation();
                if ($candidateCity !== null && $candidateCity !== '') {
                    $coords = $this->geolocationService->tryGeocode($candidateCity);
                    if ($coords !== null) {
                        $candidateLat = $coords['lat'];
                        $candidateLng = $coords['lng'];
                    }
                }
            }

            // Add distance to each card.
            foreach ($dbCards as $dbCard) {
                $dbCard['distance'] = $this->geolocationService->buildOfferWithDistance(
                    $dbCard,
                    $candidateLat,
                    $candidateLng
                )['distance'];

                if ($dbCard['is_expired'] === true) {
                    $expiredOffers[] = $dbCard;
                }

                $cards[] = $dbCard;
            }

            // Sort cards nearest → farthest for candidates (nulls go last).
            if ($role === 'candidate' && $candidateLat !== null) {
                usort($cards, static function (array $a, array $b): int {
                    $da = $a['distance'];
                    $db = $b['distance'];
                    if ($da === null && $db === null) { return 0; }
                    if ($da === null) { return 1; }
                    if ($db === null) { return -1; }
                    return $da <=> $db;
                });
            }
        } catch (\Throwable $exception) {
            // Keep UI usable even if table is not ready in current environment.
        }

        if ($role === 'recruiter') {
            try {
                $warnings = $connection->fetchAllAssociative(
                    'SELECT w.job_offer_id, w.reason, w.created_at, jo.title
                     FROM job_offer_warning w
                     INNER JOIN job_offer jo ON jo.id = w.job_offer_id
                     WHERE w.recruiter_id = :recruiter_id AND w.status IN (\'SENT\', \'SEEN\')
                     ORDER BY w.created_at DESC',
                    ['recruiter_id' => $currentRecruiterId]
                );
                $connection->update('job_offer_warning', [
                    'status' => 'SEEN',
                    'seen_at' => $now->format('Y-m-d H:i:s'),
                ], [
                    'recruiter_id' => $currentRecruiterId,
                    'status' => 'SENT',
                ]);
            } catch (\Throwable $exception) {
                $warnings = [];
            }

            // Fetch warning statuses for all recruiter's jobs (for blue highlighting when pending review)
            try {
                $resolvedWarnings = $connection->fetchAllAssociative(
                    'SELECT job_offer_id FROM job_offer_warning WHERE recruiter_id = :recruiter_id AND status = :status',
                    ['recruiter_id' => $currentRecruiterId, 'status' => 'RESOLVED']
                );
                
                foreach ($resolvedWarnings as $row) {
                    $warningStatuses[(string) $row['job_offer_id']] = 'RESOLVED';
                }
            } catch (\Throwable $exception) {
                // Keep UI usable even if query fails
            }
        }

        // Add warning status to cards
        foreach ($cards as &$card) {
            $card['warning_status'] = $warningStatuses[$card['id']] ?? null;
        }

        $filterData = (array) $filterForm->getData();
        $hasActiveFilters = trim((string) ($filterData['search'] ?? '')) !== ''
            || trim((string) ($filterData['contract_type'] ?? '')) !== ''
            || trim((string) ($filterData['status'] ?? '')) !== ''
            || trim((string) ($filterData['deadline'] ?? '')) !== '';

        return $this->render('front/modules/job_offers.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
            'filterForm' => $filterForm->createView(),
            'hasActiveFilters' => $hasActiveFilters,
            'warnings' => $warnings,
            'expiredOffers' => $expiredOffers,
            'resultCount' => count($cards),
        ]);
    }

    #[Route('/front/job-offers/statistics', name: 'front_job_offers_statistics')]
    public function jobOffersStatistics(Request $request, Job_offerRepository $jobOfferRepository): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can access offer statistics.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        $offerStats = [
            'total_published' => 0,
            'total_closed' => 0,
            'total_open' => 0,
            'closed_percentage' => 0,
            'open_percentage' => 0,
            'city_stats' => [],
            'contract_stats' => [],
        ];

        try {
            $offerStats = $jobOfferRepository->buildRecruiterOfferStats($currentRecruiterId, 50);
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to load offer statistics right now.');
        }

        return $this->render('front/modules/job_offer_statistics.html.twig', [
            'authUser' => ['role' => $role],
            'offerStats' => $offerStats,
        ]);
    }

    #[Route('/front/job-offers/statistics/export/pdf', name: 'front_job_offers_statistics_export_pdf', methods: ['GET'])]
    public function exportJobOffersStatisticsPdf(Request $request, Job_offerRepository $jobOfferRepository, Pdf $pdf): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can export offer statistics.');

            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        $offerStats = [
            'total_published' => 0,
            'total_closed' => 0,
            'total_open' => 0,
            'closed_percentage' => 0,
            'open_percentage' => 0,
            'city_stats' => [],
            'contract_stats' => [],
        ];

        try {
            $offerStats = $jobOfferRepository->buildRecruiterOfferStats($currentRecruiterId, 50);
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to export recruiter statistics right now.');

            return $this->redirectToRoute('front_job_offers_statistics', ['role' => 'recruiter']);
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

        $html = $this->renderView('pdf/recruiter_job_offer_statistics.pdf.twig', [
            'offerStats' => $offerStats,
            'generatedAt' => new \DateTimeImmutable(),
            'recruiterId' => $currentRecruiterId,
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
            'Content-Disposition' => 'attachment; filename="recruiter_offer_statistics_' . (new \DateTimeImmutable())->format('Ymd_His') . '.pdf"',
        ]);
    }

    #[Route('/front/job-offers/new', name: 'front_job_offer_new', methods: ['GET', 'POST'])]
    public function createJobOffer(Request $request, Connection $connection): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        if ($role !== 'recruiter') {
            $this->addFlash('error', 'Only recruiters can create job offers.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        $formData = [
            'title' => '',
            'contract_type' => '',
            'status' => 'open',
            'description' => '',
            'location' => '',
            'deadline' => '',
            'skills' => [['name' => '', 'level' => '']],
        ];
        $fieldErrors = [];

        if ($request->isMethod('POST')) {
            $formData = [
                'title' => trim((string) $request->request->get('title', '')),
                'contract_type' => trim((string) $request->request->get('contract_type', '')),
                'status' => trim((string) $request->request->get('status', 'open')),
                'description' => trim((string) $request->request->get('description', '')),
                'location' => trim((string) $request->request->get('location', '')),
                'deadline' => trim((string) $request->request->get('deadline', '')),
                'skills' => array_map(static function ($entry): array {
                    return [
                        'name' => trim((string) ($entry['name'] ?? '')),
                        'level' => trim((string) ($entry['level'] ?? '')),
                    ];
                }, (array) $request->request->all('skills')),
            ];

            if (count($formData['skills']) === 0) {
                $formData['skills'] = [['name' => '', 'level' => '']];
            }

            $fieldErrors = Job_offer::validateCreateFormData(
                $formData,
                self::CONTRACT_TYPES,
                self::JOB_STATUSES,
                self::SKILL_LEVELS
            );

            if (empty($fieldErrors)) {
                $deadline = date_create_from_format('Y-m-d\\TH:i', $formData['deadline']);
                if ($deadline) {
                    $now = new \DateTimeImmutable();
                    $coords = $this->resolveLocationCoordinates($formData['location']);
                    $newId = (string) ((int) round(microtime(true) * 1000) . random_int(100, 999));
                    try {
                        $connection->beginTransaction();

                        $connection->insert('job_offer', [
                            'id' => $newId,
                            'recruiter_id' => $currentRecruiterId,
                            'title' => $formData['title'],
                            'description' => $formData['description'],
                            'location' => $formData['location'],
                            'latitude' => $coords['lat'],
                            'longitude' => $coords['lng'],
                            'contract_type' => $formData['contract_type'],
                            'created_at' => $now->format('Y-m-d H:i:s'),
                            'deadline' => $deadline->format('Y-m-d H:i:s'),
                            'status' => $formData['status'],
                            'quality_score' => 100,
                            'ai_suggestions' => '',
                            'is_flagged' => 0,
                            'flagged_at' => $now->format('Y-m-d H:i:s'),
                        ]);

                        foreach ($formData['skills'] as $skill) {
                            $connection->insert('offer_skill', [
                                'id' => (string) ((int) round(microtime(true) * 1000) . random_int(100, 999)),
                                'offer_id' => $newId,
                                'skill_name' => $skill['name'],
                                'level_required' => $skill['level'],
                            ]);
                        }

                        $connection->commit();

                        $this->addFlash('success', 'Job offer created successfully.');
                        return $this->redirectToRoute('front_job_offers', ['role' => 'recruiter']);
                    } catch (\Throwable $exception) {
                        if ($connection->isTransactionActive()) {
                            $connection->rollBack();
                        }
                        $this->addFlash('error', 'Failed to create job offer. Check DB constraints for recruiter_id and offer_skill.');
                    }
                }
            }
        }

        return $this->render('front/modules/job_offer_new.html.twig', [
            'authUser' => ['role' => $role],
            'contractTypes' => self::CONTRACT_TYPES,
            'jobStatuses' => self::JOB_STATUSES,
            'skillLevels' => self::SKILL_LEVELS,
            'formData' => $formData,
            'fieldErrors' => $fieldErrors,
        ]);
    }

    #[Route('/front/job-offers/location-suggestions', name: 'front_job_offer_location_suggestions', methods: ['GET'])]
    public function suggestOfferLocations(Request $request): JsonResponse
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'recruiter') {
            return $this->json([
                'ok' => false,
                'error' => 'Only recruiters can access location suggestions.',
            ], Response::HTTP_FORBIDDEN);
        }

        $term = trim((string) $request->query->get('q', ''));
        if (mb_strlen($term) < 2) {
            return $this->json([
                'ok' => true,
                'suggestions' => [],
            ]);
        }

        $suggestions = $this->jobOfferLocationGeocoder->suggestLocations($term, 6);

        return $this->json([
            'ok' => true,
            'suggestions' => $suggestions,
        ]);
    }

    #[Route('/front/job-offers/ai-generate', name: 'front_job_offer_ai_generate', methods: ['POST'])]
    public function generateJobOfferWithAi(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'recruiter') {
            return $this->json([
                'ok' => false,
                'error' => 'Only recruiters can generate AI suggestions.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $title = trim((string) ($payload['title'] ?? ''));
        $contractType = $this->normalizeStoredContractType((string) ($payload['contract_type'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $skills = $this->normalizeSkills((array) ($payload['skills'] ?? []));
        if ($title === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Job title is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $apiKey = $this->resolveGroqApiKey();
        if ($apiKey === '') {
            return $this->json([
                'ok' => false,
                'error' => 'AI service is not configured. Add GROQ_API_KEY in your environment.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $prompt = "You are an expert HR assistant.\n"
            . "Based on the following job offer context, generate a complete and professional job offer.\n\n"
            . "INPUT:\n"
            . "Job Title: {$title}\n"
            . "Contract Type: " . ($contractType !== '' ? $contractType : 'Not specified') . "\n"
            . "Location: " . ($location !== '' ? $location : 'Not specified') . "\n"
            . "Required Skills: " . $this->formatSkillsForPrompt($skills) . "\n\n"
            . "OUTPUT (JSON format only):\n"
            . "{\n"
            . "  \"description\": \"Professional and detailed job description (5-8 lines)\",\n"
            . "  \"required_skills\": [\"skill1\", \"skill2\", \"skill3\", \"skill4\"],\n"
            . "  \"soft_skills\": [\"skill1\", \"skill2\"],\n"
            . "  \"experience_level\": \"Junior | Mid | Senior\",\n"
            . "  \"contract_type\": \"CDI | CDD | Internship | Freelance | Full-time | Part-time | Remote Contract\",\n"
            . "  \"suggested_location\": \"City name\",\n"
            . "  \"keywords\": [\"tag1\", \"tag2\", \"tag3\"]\n"
            . "}\n\n"
            . "RULES:\n"
            . "- Make the description clear and professional\n"
            . "- Use realistic HR vocabulary\n"
            . "- Skills must be relevant to the job title\n"
            . "- Return ONLY valid JSON\n";

        try {
            $aiResult = $this->requestGroqGenerateJobOffer($httpClient, $apiKey, $prompt);
            if ($aiResult['ok'] !== true) {
                return $this->json([
                    'ok' => false,
                    'error' => $aiResult['error'],
                ], Response::HTTP_BAD_GATEWAY);
            }

            $rawText = trim($aiResult['text']);
            $decoded = $this->decodeAiJsonPayload($rawText);
            if (!is_array($decoded)) {
                return $this->json([
                    'ok' => false,
                    'error' => 'AI response format is invalid.',
                ], Response::HTTP_BAD_GATEWAY);
            }

            return $this->json([
                'ok' => true,
                'data' => $this->normalizeAiJobOfferPayload($decoded, $title),
            ]);
        } catch (\Throwable) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to generate offer suggestions. Please try again.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/front/job-offers/{id}/edit', name: 'front_job_offer_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editJobOffer(string $id, Request $request, Connection $connection): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        if ($role !== 'recruiter') {
            $this->addFlash('error', 'Only recruiters can update job offers.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        $offer = $connection->fetchAssociative(
            'SELECT id, recruiter_id, title, description, location, contract_type, deadline FROM job_offer WHERE id = :id AND recruiter_id = :recruiter_id LIMIT 1',
            [
                'id' => $id,
                'recruiter_id' => $currentRecruiterId,
            ]
        );
        $skills = $connection->fetchAllAssociative(
            'SELECT skill_name, level_required FROM offer_skill WHERE offer_id = :offer_id ORDER BY id ASC',
            ['offer_id' => $id]
        );

        if (!$offer) {
            $this->addFlash('error', 'You can update only job offers created by you.');
            return $this->redirectToRoute('front_job_offers', ['role' => 'recruiter']);
        }

        $deadlineValue = (string) ($offer['deadline'] ?? '');
        if ($deadlineValue !== '' && str_contains($deadlineValue, ' ')) {
            $deadlineValue = substr(str_replace(' ', 'T', $deadlineValue), 0, 16);
        }

        $formData = [
            'title' => (string) ($offer['title'] ?? ''),
            'contract_type' => $this->normalizeStoredContractType((string) ($offer['contract_type'] ?? '')),
            'description' => (string) ($offer['description'] ?? ''),
            'location' => (string) ($offer['location'] ?? ''),
            'deadline' => $deadlineValue,
            'skills' => array_map(static function (array $skill): array {
                return [
                    'name' => (string) ($skill['skill_name'] ?? ''),
                    'level' => self::normalizeStoredSkillLevel((string) ($skill['level_required'] ?? '')),
                ];
            }, $skills),
        ];
        if (count($formData['skills']) === 0) {
            $formData['skills'] = [['name' => '', 'level' => '']];
        }

        $fieldErrors = [];

        if ($request->isMethod('POST')) {
            $formData = [
                'title' => trim((string) $request->request->get('title', '')),
                'contract_type' => trim((string) $request->request->get('contract_type', '')),
                'description' => trim((string) $request->request->get('description', '')),
                'location' => trim((string) $request->request->get('location', '')),
                'deadline' => trim((string) $request->request->get('deadline', '')),
                'skills' => array_map(static function ($entry): array {
                    return [
                        'name' => trim((string) ($entry['name'] ?? '')),
                        'level' => trim((string) ($entry['level'] ?? '')),
                    ];
                }, (array) $request->request->all('skills')),
            ];

            if (count($formData['skills']) === 0) {
                $formData['skills'] = [['name' => '', 'level' => '']];
            }

            $fieldErrors = Job_offer::validateEditFormData(
                $formData,
                self::CONTRACT_TYPES,
                self::SKILL_LEVELS
            );

            if (empty($fieldErrors)) {
                $deadline = date_create_from_format('Y-m-d\\TH:i', $formData['deadline']);
                if ($deadline) {
                    $coords = $this->resolveLocationCoordinates($formData['location']);

                    try {
                        $connection->beginTransaction();

                        $connection->update('job_offer', [
                            'title' => $formData['title'],
                            'description' => $formData['description'],
                            'location' => $formData['location'],
                            'contract_type' => $formData['contract_type'],
                            'deadline' => $deadline->format('Y-m-d H:i:s'),
                            'latitude' => $coords['lat'],
                            'longitude' => $coords['lng'],
                        ], [
                            'id' => $id,
                            'recruiter_id' => $currentRecruiterId,
                        ]);

                        $connection->delete('offer_skill', ['offer_id' => $id]);
                        foreach ($formData['skills'] as $skill) {
                            $connection->insert('offer_skill', [
                                'id' => (string) ((int) round(microtime(true) * 1000) . random_int(100, 999)),
                                'offer_id' => $id,
                                'skill_name' => $skill['name'],
                                'level_required' => $skill['level'],
                            ]);
                        }

                        // Keep offer edit successful even if warning tables differ in current DB.
                        $hasActiveWarning = 0;
                        try {
                            $activeWarning = $connection->fetchAssociative(
                                'SELECT id, status FROM job_offer_warning WHERE job_offer_id = :job_offer_id AND recruiter_id = :recruiter_id AND status IN (\'SENT\', \'SEEN\') ORDER BY created_at DESC LIMIT 1',
                                ['job_offer_id' => $id, 'recruiter_id' => $currentRecruiterId]
                            );

                            if ($activeWarning) {
                                $hasActiveWarning = 1;
                                $submittedAt = new \DateTimeImmutable();

                                try {
                                    $connection->delete('warning_correction', [
                                        'warning_id' => (string) $activeWarning['id'],
                                        'status' => 'PENDING',
                                    ]);
                                    $connection->insert('warning_correction', [
                                        'id' => (string) ((int) round(microtime(true) * 1000) . random_int(100, 999)),
                                        'warning_id' => (string) $activeWarning['id'],
                                        'job_offer_id' => $id,
                                        'recruiter_id' => $currentRecruiterId,
                                        'correction_note' => 'Recruiter submitted an updated offer after admin warning.',
                                        'old_title' => (string) ($offer['title'] ?? ''),
                                        'new_title' => $formData['title'],
                                        'old_description' => (string) ($offer['description'] ?? ''),
                                        'new_description' => $formData['description'],
                                        'status' => 'PENDING',
                                        'submitted_at' => $submittedAt->format('Y-m-d H:i:s'),
                                        'reviewed_at' => $submittedAt->format('Y-m-d H:i:s'),
                                        'admin_note' => '',
                                    ]);
                                } catch (\Throwable) {
                                    // The warning status is enough to notify admin review if correction history is unavailable.
                                }

                                $connection->executeStatement(
                                    'UPDATE job_offer_warning
                                     SET status = :resolved_status, resolved_at = :resolved_at
                                     WHERE job_offer_id = :job_offer_id
                                       AND recruiter_id = :recruiter_id
                                       AND status IN (\'SENT\', \'SEEN\')',
                                    [
                                        'resolved_status' => 'RESOLVED',
                                        'resolved_at' => $submittedAt->format('Y-m-d H:i:s'),
                                        'job_offer_id' => $id,
                                        'recruiter_id' => $currentRecruiterId,
                                    ]
                                );
                            }
                        } catch (\Throwable) {
                            $hasActiveWarning = 0;
                        }

                        $connection->commit();

                        $this->addFlash('success', 'Job offer updated successfully.' . ($hasActiveWarning > 0 ? ' Admin has been notified about the changes.' : ''));
                        return $this->redirectToRoute('front_job_offers', ['role' => 'recruiter']);
                    } catch (\Throwable $exception) {
                        if ($connection->isTransactionActive()) {
                            $connection->rollBack();
                        }
                        $this->addFlash('error', 'Unable to update this job offer.');
                    }
                }
            }
        }

        return $this->render('front/modules/job_offer_edit.html.twig', [
            'authUser' => ['role' => $role],
            'offer' => $offer,
            'skills' => $skills,
            'contractTypes' => self::CONTRACT_TYPES,
            'skillLevels' => self::SKILL_LEVELS,
            'formData' => $formData,
            'fieldErrors' => $fieldErrors,
        ]);
    }

    #[Route('/front/job-offers/{id}/delete', name: 'front_job_offer_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteJobOffer(string $id, Request $request, Connection $connection, LoggerInterface $logger): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        if ($role !== 'recruiter') {
            $this->addFlash('error', 'Only recruiters can delete job offers.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        try {
            $offerExists = $connection->fetchOne(
                'SELECT id FROM job_offer WHERE id = :id AND recruiter_id = :recruiter_id LIMIT 1',
                ['id' => $id, 'recruiter_id' => $currentRecruiterId]
            );

            if ($offerExists === false || $offerExists === null || (string) $offerExists === '') {
                $this->addFlash('error', 'You can delete only job offers created by you.');

                return $this->redirectToRoute('front_job_offers', ['role' => 'recruiter']);
            }

            $connection->beginTransaction();

            $connection->executeStatement(
                'DELETE FROM interview_feedback
                 WHERE interview_id IN (
                     SELECT interview.id
                     FROM interview
                     INNER JOIN job_application ON job_application.id = interview.application_id
                     WHERE job_application.offer_id = :offer_id
                 )',
                ['offer_id' => $id]
            );
            $connection->executeStatement(
                'DELETE FROM interview
                 WHERE application_id IN (
                     SELECT id FROM job_application WHERE offer_id = :offer_id
                 )',
                ['offer_id' => $id]
            );
            $connection->executeStatement(
                'DELETE FROM application_status_history
                 WHERE application_id IN (
                     SELECT id FROM job_application WHERE offer_id = :offer_id
                 )',
                ['offer_id' => $id]
            );
            $connection->delete('job_application', ['offer_id' => $id]);
            $connection->delete('warning_correction', ['job_offer_id' => $id]);
            $connection->delete('job_offer_warning', ['job_offer_id' => $id]);
            $connection->delete('offer_skill', ['offer_id' => $id]);
            $deletedRows = $connection->delete('job_offer', [
                'id' => $id,
                'recruiter_id' => $currentRecruiterId,
            ]);

            if ($deletedRows > 0) {
                $connection->commit();
                $this->addFlash('success', 'Job offer deleted successfully.');
            } else {
                $connection->rollBack();
                $this->addFlash('error', 'You can delete only job offers created by you.');
            }
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $logger->error('Job offer deletion failed.', [
                'offer_id' => $id,
                'recruiter_id' => $currentRecruiterId,
                'error_message' => $exception->getMessage(),
            ]);

            $this->addFlash('error', 'Unable to delete this job offer.');
        }

        return $this->redirectToRoute('front_job_offers', ['role' => 'recruiter']);
    }

    #[Route('/front/job-applications', name: 'front_job_applications')]
    public function jobApplications(Request $request): Response
    {
        $role = $this->resolveSessionRole($request);
        $cards = [];
        $rankingEnabled = false;
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 6;
        $pagination = [
            'enabled' => false,
            'current_page' => 1,
            'total_pages' => 1,
            'has_previous' => false,
            'has_next' => false,
            'previous_page' => 1,
            'next_page' => 1,
            'pages' => [1],
            'route_params_base' => ['role' => $role],
        ];
        $applicationFilters = [
            'search' => '',
            'status' => 'all',
            'sort' => 'date_desc',
            'ai_rank' => false,
        ];

        if ($role === 'recruiter') {
            // RECRUITER VIEW: Show applications for their job offers with interview creation capability
            $recruiter = $this->resolveCurrentRecruiter($request);

            if ($recruiter instanceof Recruiter) {
                $search = trim((string) $request->query->get('search', ''));
                $status = strtolower(trim((string) $request->query->get('status', 'all')));
                $sort = strtolower(trim((string) $request->query->get('sort', 'date_desc')));
                $aiRankFlag = strtolower(trim((string) $request->query->get('ai_rank', '0')));
                $rankingEnabled = in_array($aiRankFlag, ['1', 'true', 'yes', 'on'], true);

                $allowedStatuses = ['all', 'submitted', 'in_review', 'shortlisted', 'rejected', 'interview', 'hired'];
                if (!in_array($status, $allowedStatuses, true)) {
                    $status = 'all';
                }

                $allowedSorts = ['date_desc', 'date_asc', 'title_asc', 'title_desc', 'status_asc', 'status_desc'];
                if (!in_array($sort, $allowedSorts, true)) {
                    $sort = 'date_desc';
                }

                $applicationFilters = [
                    'search' => $search,
                    'status' => $status,
                    'sort' => $sort,
                    'ai_rank' => $rankingEnabled,
                ];

                $pagination['route_params_base'] = [
                    'role' => 'recruiter',
                    'search' => $search,
                    'status' => $status,
                    'sort' => $sort,
                    'ai_rank' => $rankingEnabled ? '1' : '0',
                ];

                $statusForQuery = $status === 'all' ? 'all' : strtoupper($status);
                /** @var Job_applicationRepository $applicationRepository */
                $applicationRepository = $this->doctrine->getRepository(Job_application::class);
                $queryBuilder = $applicationRepository->createRecruiterListingQueryBuilder($recruiter, $search, $statusForQuery, $sort);
                $applications = [];
                $rankingsByApplicationId = [];

                if ($rankingEnabled) {
                    $applications = $queryBuilder->getQuery()->getResult();

                    if (count($applications) > 0) {
                        try {
                            $rankingPayload = $this->applicationAiRankingService->rankApplications($applications);
                            $rankingsByApplicationId = $rankingPayload['results'];

                            usort($applications, static function (Job_application $left, Job_application $right) use ($rankingsByApplicationId): int {
                                $leftScore = (int) ($rankingsByApplicationId[(string) $left->getId()]['score'] ?? -1);
                                $rightScore = (int) ($rankingsByApplicationId[(string) $right->getId()]['score'] ?? -1);

                                return $rightScore <=> $leftScore;
                            });

                            $errors = $rankingPayload['errors'];
                            if ($errors !== []) {
                                $this->addFlash('warning', sprintf(
                                    'AI ranking completed with %d fallback case(s). %s',
                                    count($errors),
                                    $errors[0]
                                ));
                            }
                        } catch (Throwable $exception) {
                            $rankingEnabled = false;
                            $applicationFilters['ai_rank'] = false;
                            $pagination['route_params_base']['ai_rank'] = '0';
                            $rankingsByApplicationId = [];
                            $this->addFlash('error', 'AI ranking is unavailable right now. ' . $exception->getMessage());
                        }
                    }

                    if ($rankingEnabled) {
                        $pager = $this->createPagerFromArray($applications, $page, $perPage);
                        $applications = iterator_to_array($pager->getCurrentPageResults());
                        $pagination = $this->buildPaginationView($pager, $pagination['route_params_base']);
                    }
                }

                if (!$rankingEnabled) {
                    $pager = $this->createPagerFromQueryBuilder($queryBuilder, $page, $perPage);
                    $applications = iterator_to_array($pager->getCurrentPageResults());
                    $pagination = $this->buildPaginationView($pager, $pagination['route_params_base']);
                }

                foreach ($applications as $application) {
                    $offer = $application->getOffer_id();
                    $candidate = $application->getCandidate_id();
                    $candidateName = 'Candidate';
                    if ($candidate instanceof Candidate) {
                        $fullName = trim((string) $candidate->getFirstName() . ' ' . (string) $candidate->getLastName());
                        if ($fullName !== '') {
                            $candidateName = $fullName;
                        }
                    }

                    // Check if interview already exists (one interview per application rule)
                    $hasActiveInterview = $this->hasActiveInterviewForApplication($application);
                    $createInterviewUrl = $this->generateUrl('front_interview_create', [
                        'applicationId' => (string) $application->getId(),
                        'role' => $role
                    ] + $request->query->all());
                    $createInterviewCheckUrl = $this->generateUrl('front_application_interview_availability', [
                        'applicationId' => (string) $application->getId(),
                        'role' => $role,
                    ] + $request->query->all());
                    $acceptUrl = $this->generateUrl('front_application_set_status', [
                        'applicationId' => (string) $application->getId(),
                        'status' => 'accepted',
                        'role' => $role
                    ] + $request->query->all());
                    $declineUrl = $this->generateUrl('front_application_set_status', [
                        'applicationId' => (string) $application->getId(),
                        'status' => 'declined',
                        'role' => $role
                    ] + $request->query->all());

                    $applicationId = (string) $application->getId();
                    $ranking = $rankingsByApplicationId[$applicationId] ?? null;
                    $score = is_array($ranking) && isset($ranking['score']) ? (int) $ranking['score'] : null;
                    $rationale = is_array($ranking) ? trim((string) ($ranking['rationale'] ?? '')) : '';
                    $breakdown = is_array($ranking) && is_array($ranking['breakdown'] ?? null) ? $ranking['breakdown'] : [];
                    $matchedSkills = is_array($ranking) && is_array($ranking['matched_skills'] ?? null)
                        ? $ranking['matched_skills']
                        : [];
                    $missingSkills = is_array($ranking) && is_array($ranking['missing_skills'] ?? null)
                        ? $ranking['missing_skills']
                        : [];

                    $detailExtra = [
                        'Status: ' . (string) $application->getCurrent_status(),
                        'Offer: ' . ($offer ? (string) $offer->getTitle() : 'Unknown Offer'),
                        'Candidate: ' . $candidateName,
                        'Applied At: ' . $application->getApplied_at()->format('d M Y H:i'),
                        'Phone: ' . (string) $application->getPhone(),
                    ];

                    if ($score !== null) {
                        $detailExtra[] = 'AI Score: ' . $score . '/100';
                        if ($rationale !== '') {
                            $detailExtra[] = 'AI Rationale: ' . $rationale;
                        }
                        $detailExtra[] = sprintf(
                            'AI Breakdown: Skills %d | Experience %d | Education %d | Cover Letter %d | CV %d',
                            (int) ($breakdown['skill_match'] ?? 0),
                            (int) ($breakdown['experience_relevance'] ?? 0),
                            (int) ($breakdown['education_fit'] ?? 0),
                            (int) ($breakdown['cover_letter_relevance'] ?? 0),
                            (int) ($breakdown['cv_relevance'] ?? 0)
                        );

                        if ($matchedSkills !== []) {
                            $detailExtra[] = 'Matched Skills: ' . implode(', ', array_slice(array_map('strval', $matchedSkills), 0, 8));
                        }

                        if ($missingSkills !== []) {
                            $detailExtra[] = 'Missing Skills: ' . implode(', ', array_slice(array_map('strval', $missingSkills), 0, 8));
                        }

                        $detailExtra[] = 'Inputs Used: job title, job description, required skills, candidate skills, experience years, education level, cover letter text, cv extracted text';
                    }

                    $meta = (string) $application->getCurrent_status();
                    if ($score !== null) {
                        $meta .= ' | AI ' . $score . '/100';
                    }

                    $cards[] = [
                        'id' => $applicationId,
                        'meta' => $meta,
                        'title' => 'Offer: ' . ($offer ? $offer->getTitle() : 'Unknown Offer'),
                        'text' => $candidateName . ' | Applied on ' . $application->getApplied_at()->format('d M Y H:i') . ' | Phone: ' . $application->getPhone(),
                        'status' => (string) $application->getCurrent_status(),
                        'detail_extra' => $detailExtra,
                        'ai_score' => $score,
                        'ai_rationale' => $rationale,
                        'ai_breakdown' => $breakdown,
                        // From application-mangement: recruiter details
                        'details_url' => $this->generateUrl('app_recruiter_application_details', ['applicationId' => $application->getId()]),
                        'shortlist_url' => $this->generateUrl('app_recruiter_application_update_status', ['applicationId' => $application->getId()]),
                        'reject_url' => $this->generateUrl('app_recruiter_application_update_status', ['applicationId' => $application->getId()]),
                        // From interview: interview creation with one-per-application rule
                        'create_interview_url' => $hasActiveInterview ? '#' : $createInterviewUrl,
                        'create_interview_check_url' => $createInterviewCheckUrl,
                        'can_create_interview' => !$hasActiveInterview,
                        'interview_block_reason' => $hasActiveInterview ? 'Interview already created for this application.' : '',
                        'accept_url' => $acceptUrl,
                        'decline_url' => $declineUrl,
                    ];
                }
            }
        } elseif ($role === 'admin') {
            // ADMIN VIEW: read-only access to all applications with full inspection link
            /** @var Job_applicationRepository $applicationRepository */
            $applicationRepository = $this->doctrine->getRepository(Job_application::class);
            $queryBuilder = $applicationRepository->createAdminListingQueryBuilder();
            $pager = $this->createPagerFromQueryBuilder($queryBuilder, $page, $perPage);
            $applications = iterator_to_array($pager->getCurrentPageResults());
            $pagination = $this->buildPaginationView($pager, ['role' => 'admin']);

            foreach ($applications as $application) {
                $offer = $application->getOffer_id();
                $candidate = $application->getCandidate_id();
                $candidateName = 'Candidate';

                if ($candidate instanceof Candidate) {
                    $fullName = trim((string) $candidate->getFirstName() . ' ' . (string) $candidate->getLastName());
                    if ($fullName !== '') {
                        $candidateName = $fullName;
                    }
                }

                $offerTitle = $offer ? (string) $offer->getTitle() : 'Unknown Offer';
                $status = (string) $application->getCurrent_status();
                $appliedAt = $application->getApplied_at();

                $cards[] = [
                    'id' => (string) $application->getId(),
                    'meta' => $status,
                    'title' => 'Offer: ' . $offerTitle,
                    'text' => $candidateName . ' | Applied on ' . $appliedAt->format('d M Y H:i') . ' | Phone: ' . $application->getPhone(),
                    'status' => $status,
                    'details_url' => $this->generateUrl('management_job_applications_details', ['applicationId' => $application->getId()]),
                ];
            }
        } else {
            // CANDIDATE VIEW: Show their own applications with edit/withdraw options
            $candidate = $this->resolveCurrentCandidate($request);

            if ($candidate instanceof Candidate) {
                /** @var Job_applicationRepository $applicationRepository */
                $applicationRepository = $this->doctrine->getRepository(Job_application::class);
                $queryBuilder = $applicationRepository->createCandidateListingQueryBuilder($candidate);
                $pager = $this->createPagerFromQueryBuilder($queryBuilder, $page, $perPage);
                $applications = iterator_to_array($pager->getCurrentPageResults());
                $pagination = $this->buildPaginationView($pager, ['role' => 'candidate']);

                foreach ($applications as $application) {
                    $offer = $application->getOffer_id();
                    $offerTitle = $offer ? $offer->getTitle() : 'Unknown Offer';
                    $status = $application->getCurrent_status();
                    $appliedAt = $application->getApplied_at();
                    // Can only edit/withdraw if status is SUBMITTED
                    $canWithdraw = strtoupper(trim((string) $status)) === 'SUBMITTED';
                    $canEdit = strtoupper(trim((string) $status)) === 'SUBMITTED';

                    $cards[] = [
                        'id' => (string) $application->getId(),
                        'meta' => $status,
                        'title' => 'Offer: ' . $offerTitle,
                        'text' => 'Applied on ' . $appliedAt->format('d M Y H:i') . ' | Phone: ' . $application->getPhone(),
                        'status' => (string) $status,
                        // From application-mangement: candidate details, edit, withdraw
                        'details_url' => $this->generateUrl('app_candidate_application_details', ['applicationId' => $application->getId()]),
                        'can_edit' => $canEdit,
                        'edit_url' => $canEdit
                            ? $this->generateUrl('app_candidate_application_edit', ['applicationId' => $application->getId()])
                            : '#',
                        'can_withdraw' => $canWithdraw,
                        'withdraw_url' => $canWithdraw
                            ? $this->generateUrl('app_candidate_application_withdraw', ['applicationId' => $application->getId()])
                            : '#',
                    ];
                }
            }
        }

        return $this->render('front/modules/job_applications.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
            'applicationFilters' => $applicationFilters,
            'aiRankingEnabled' => $rankingEnabled,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/front/events', name: 'front_events')]
    public function events(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = $this->resolveSessionRole($request);
        $session = $request->getSession();
        $registeredIds = [];

        $candidate = $this->resolveCurrentCandidate($request);
        $myRegs = $candidate instanceof Candidate
            ? $entityManager->getRepository(Event_registration::class)->findBy(['candidate_id' => $candidate])
            : [];

        if (count($myRegs) > 0) {
            foreach ($myRegs as $registration) {
                $registeredIds[] = $registration->getEvent_id()->getId();
            }
            $session->set('registered_event_ids', $registeredIds);
        }

        if ($role === 'recruiter') {
            $recruiter = $this->resolveCurrentRecruiter($request);
            if (!$recruiter instanceof Recruiter) {
                $events = [];
            } else {
                $events = $entityManager->getRepository(Recruitment_event::class)->findBy([
                    'recruiter_id' => $recruiter,
                ], ['id' => 'DESC'], self::EVENT_LIST_LIMIT);
            }
        } else {
            $events = $entityManager->getRepository(Recruitment_event::class)->findBy([], ['id' => 'DESC'], self::EVENT_LIST_LIMIT);
        }

        $cards = [];
        foreach ($events as $event) {
            $eventDate = $event->getEvent_date();
            if (!$eventDate instanceof \DateTimeInterface) {
                continue;
            }

            $description = trim((string) $event->getDescription());
            $capacity = (int) $event->getCapacity();
            $registrationCount = $this->countActiveEventRegistrations($event);
            $isFull = $capacity > 0 && $registrationCount >= $capacity;
            $fillRatio = $capacity > 0 ? $registrationCount / $capacity : 0.0;

            $cards[] = [
                'id' => $event->getId(),
                'meta' => sprintf('%s | %s', $eventDate->format('d M Y'), (string) $event->getLocation()),
                'title' => (string) $event->getTitle(),
                'text' => $description === '' ? 'No event description available yet.' : substr($description, 0, 190),
                'event_type' => (string) $event->getEvent_type(),
                'location' => (string) $event->getLocation(),
                'capacity' => $capacity,
                'registration_count' => $registrationCount,
                'remaining_spots' => $capacity > 0 ? max(0, $capacity - $registrationCount) : null,
                'is_full' => $isFull,
                'is_popular' => $registrationCount >= 3 || ($registrationCount > 0 && $fillRatio >= 0.5),
                'popularity_score' => $registrationCount,
                'meet_link' => (string) $event->getMeet_link(),
                'event_date_value' => $eventDate->format('Y-m-d\TH:i'),
                'registered' => in_array($event->getId(), $registeredIds, true),
            ];
        }

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events/register/{id}', name: 'front_event_register', methods: ['POST'])]
    public function registerEvent(
        Request $request,
        int $id,
        EntityManagerInterface $entityManager,
        NotifierInterface $notifier,
        LoggerInterface $logger
    ): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'candidate') {
            $this->addFlash('warning', 'Only candidates can register to events.');
            return $this->redirectToRoute('front_events');
        }

        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $session = $request->getSession();
        $registeredIds = $session->get('registered_event_ids', []);

        $candidate = $this->resolveCurrentCandidate($request);

        if (!$candidate instanceof Candidate) {
            $this->addFlash('warning', 'Please log in with a candidate account to register for events.');
            return $this->redirectToRoute('app_login');
        }

        $registrationRepository = $entityManager->getRepository(Event_registration::class);
        $queryBuilder = $registrationRepository->createQueryBuilder('er')
            ->where('IDENTITY(er.event_id) = :eventId')
            ->andWhere('IDENTITY(er.candidate_id) = :candidateId')
            ->setParameter('eventId', $event->getId())
            ->setParameter('candidateId', $candidate->getId());

        $existing = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$existing) {
            $capacity = (int) $event->getCapacity();
            $registrationCount = $this->countActiveEventRegistrations($event);
            if ($capacity > 0 && $registrationCount >= $capacity) {
                $message = sprintf('"%s" is full. Registration is closed for this event.', $event->getTitle());
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['success' => false, 'warning' => true, 'message' => $message], Response::HTTP_CONFLICT);
                }

                $this->addFlash('warning', $message);
                return $this->redirectToRoute('front_events', ['role' => 'candidate']);
            }

            $registration = new Event_registration();
            $registration->setId($this->nextNumericId(Event_registration::class));
            $registration->setEvent_id($event);
            $registration->setCandidate_id($candidate);
            $registration->setRegistered_at(new \DateTime());
            $registration->setAttendance_status('registered');

            $entityManager->persist($registration);
            $entityManager->flush();

            $this->sendEventRegistrationBundleNotification($notifier, $logger, $registration, 'registered');

            if (!in_array($id, $registeredIds, true)) {
                $registeredIds[] = $id;
                $session->set('registered_event_ids', $registeredIds);
            }

            $message = sprintf('You have successfully registered for "%s".', $event->getTitle());
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'message' => $message]);
            }
            $this->addFlash('success', $message);
        } else {
            if (!in_array($id, $registeredIds, true)) {
                $registeredIds[] = $id;
                $session->set('registered_event_ids', $registeredIds);
            }

            $message = sprintf('You are already registered for "%s".', $event->getTitle());
            if ($request->isXmlHttpRequest()) {
                return $this->json(['warning' => true, 'message' => $message]);
            }
            $this->addFlash('warning', $message);
        }

        return $this->redirectToRoute('front_events', ['role' => 'candidate']);
    }

    #[Route('/front/events/unregister/{id}', name: 'front_event_unregister', methods: ['POST'])]
    public function unregisterEvent(
        Request $request,
        int $id,
        EntityManagerInterface $entityManager,
        NotifierInterface $notifier,
        LoggerInterface $logger
    ): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'candidate') {
            $this->addFlash('warning', 'Only candidates can cancel event registrations.');
            return $this->redirectToRoute('front_events');
        }

        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $session = $request->getSession();
        $registeredIds = $session->get('registered_event_ids', []);
        $registeredIds = array_values(array_filter($registeredIds, static fn ($registeredId): bool => (int) $registeredId !== $id));
        $session->set('registered_event_ids', $registeredIds);

        $candidate = $this->resolveCurrentCandidate($request);

        if ($candidate instanceof Candidate) {
            $registration = $entityManager->getRepository(Event_registration::class)->findOneBy([
                'event_id' => $event,
                'candidate_id' => $candidate,
            ]);

            if ($registration) {
                $this->sendEventRegistrationBundleNotification($notifier, $logger, $registration, 'unregistered');
                $entityManager->remove($registration);
                $entityManager->flush();
            }
        }

        $this->addFlash('success', sprintf('You have cancelled registration for "%s".', $event->getTitle()));
        return $this->redirectToRoute('front_event_registrations', ['role' => 'candidate']);
    }

    private function countActiveEventRegistrations(Recruitment_event $event): int
    {
        $count = 0;
        foreach ($event->getEvent_registrations() as $registration) {
            if ($this->isActiveEventRegistrationStatus($registration->getAttendance_status())) {
                $count += 1;
            }
        }

        return $count;
    }

    private function isActiveEventRegistrationStatus(?string $status): bool
    {
        $normalized = strtolower(trim((string) $status));

        return !in_array($normalized, ['rejected', 'cancelled', 'canceled', 'no_show'], true);
    }

    private function sendEventRegistrationBundleNotification(
        NotifierInterface $notifier,
        LoggerInterface $logger,
        Event_registration $registration,
        string $action
    ): void {
        $event = $registration->getEvent_id();
        $candidate = $registration->getCandidate_id();
        if (!$candidate instanceof Candidate) {
            return;
        }

        $recruiter = $event->getRecruiter_id();

        $recruiterEmail = trim((string) $recruiter->getEmail());
        if (!filter_var($recruiterEmail, FILTER_VALIDATE_EMAIL)) {
            $logger->warning('Event registration notification skipped: recruiter email is invalid.', [
                'registration_id' => $registration->getId(),
                'event_id' => $event->getId(),
                'recruiter_id' => $recruiter->getId(),
                'recruiter_email' => $recruiterEmail,
            ]);

            return;
        }

        $candidateName = $this->formatCandidateName($candidate);
        $candidateEmail = trim((string) $candidate->getEmail());
        $eventTitle = trim((string) $event->getTitle());
        $eventDate = $event->getEvent_date() instanceof \DateTimeInterface
            ? $event->getEvent_date()->format('F j, Y \\a\\t H:i')
            : 'date not set';
        $isUnregister = $action === 'unregistered';
        $subject = $isUnregister
            ? sprintf('Event registration cancelled: %s', $eventTitle !== '' ? $eventTitle : 'Recruitment event')
            : sprintf('New event registration: %s', $eventTitle !== '' ? $eventTitle : 'Recruitment event');
        $content = sprintf(
            "%s %s for \"%s\".\n\nCandidate: %s\nEmail: %s\nEvent date: %s\nRegistration ID: %s",
            $candidateName,
            $isUnregister ? 'cancelled their registration' : 'registered',
            $eventTitle !== '' ? $eventTitle : 'Recruitment event',
            $candidateName,
            $candidateEmail !== '' ? $candidateEmail : 'N/A',
            $eventDate,
            (string) $registration->getId()
        );

        try {
            $notification = (new Notification($subject, ['email']))
                ->content($content)
                ->importance($isUnregister ? Notification::IMPORTANCE_MEDIUM : Notification::IMPORTANCE_HIGH);

            $notifier->send($notification, new Recipient($recruiterEmail));
        } catch (\Throwable $exception) {
            $logger->error('Event registration notification failed.', [
                'registration_id' => $registration->getId(),
                'event_id' => $event->getId(),
                'candidate_id' => $candidate->getId(),
                'recruiter_id' => $recruiter->getId(),
                'action' => $action,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    #[Route('/front/events/registrations', name: 'front_event_registrations')]
    public function registrations(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'candidate') {
            $this->addFlash('warning', 'Only candidates can access personal event registrations.');
            return $this->redirectToRoute('front_events');
        }

        $session = $request->getSession();

        $candidate = $this->resolveCurrentCandidate($request);
        $registeredIds = [];

        $myRegs = $candidate instanceof Candidate
            ? $entityManager->getRepository(Event_registration::class)->findBy(['candidate_id' => $candidate])
            : [];

        if (count($myRegs) > 0) {
            foreach ($myRegs as $registration) {
                $registeredIds[] = $registration->getEvent_id()->getId();
            }
            $session->set('registered_event_ids', $registeredIds);
        }

        $cards = [];
        if (count($myRegs) > 0) {
            foreach ($myRegs as $registration) {
                $event = $registration->getEvent_id();
                $eventDate = $event->getEvent_date();
                if (!$eventDate instanceof \DateTimeInterface) {
                    continue;
                }

                $cards[] = [
                    'id' => $event->getId(),
                    'meta' => $eventDate->format('d M Y') . ' | ' . $event->getLocation(),
                    'title' => $event->getTitle(),
                    'text' => $event->getDescription(),
                    'event_type' => $event->getEvent_type(),
                    'location' => $event->getLocation(),
                    'capacity' => $event->getCapacity(),
                    'meet_link' => $event->getMeet_link(),
                    'event_date_value' => $eventDate->format('Y-m-d\TH:i'),
                    'status' => $registration->getAttendance_status(),
                ];
            }

            usort($cards, static fn (array $a, array $b): int => strcmp($a['event_date_value'], $b['event_date_value']));
        }

        return $this->render('front/modules/event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events/unregister-all', name: 'front_event_unregister_all', methods: ['POST'])]
    public function unregisterAllEvents(
        Request $request,
        EntityManagerInterface $entityManager,
        NotifierInterface $notifier,
        LoggerInterface $logger
    ): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'candidate') {
            $this->addFlash('warning', 'Only candidates can cancel event registrations.');
            return $this->redirectToRoute('front_events');
        }

        $session = $request->getSession();
        $session->set('registered_event_ids', []);

        $candidate = $this->resolveCurrentCandidate($request);
        $registrations = $candidate instanceof Candidate
            ? $entityManager->getRepository(Event_registration::class)->findBy(['candidate_id' => $candidate])
            : [];

        if (count($registrations) > 0) {

            foreach ($registrations as $registration) {
                $this->sendEventRegistrationBundleNotification($notifier, $logger, $registration, 'unregistered');
                $entityManager->remove($registration);
            }
            $entityManager->flush();
        }

        $this->addFlash('success', 'You have cancelled registration for all events.');
        return $this->redirectToRoute('front_event_registrations', ['role' => 'candidate']);
    }

    #[Route('/front/recruiter/event-registrations', name: 'recruiter_event_registrations')]
    public function recruiterEventRegistrations(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can view event registrations.');
            return $this->redirectToRoute('front_events');
        }

        $recruiter = $this->resolveCurrentRecruiter($request);
        if (!$recruiter instanceof Recruiter) {
            $events = [];
        } else {
            $events = $entityManager->getRepository(Recruitment_event::class)->findBy([
                'recruiter_id' => $recruiter,
            ], ['id' => 'DESC']);
        }

        $now = new \DateTimeImmutable();
        $urgentNotifications = [];
        $eventsData = [];
        foreach ($events as $event) {
            $eventDate = $event->getEvent_date();
            if (!$eventDate instanceof \DateTimeInterface) {
                continue;
            }

            $registrations = $event->getEvent_registrations();

            $candidatesList = [];
            foreach ($registrations as $registration) {
                $candidateEntity = $registration->getCandidate_id();
                $candidateFullName = '';
                $candidateEmail = '';

                if ($candidateEntity instanceof Candidate) {
                    $candidateFullName = trim(((string) $candidateEntity->getFirstName()) . ' ' . ((string) $candidateEntity->getLastName()));
                    if ($candidateFullName === '') {
                        $candidateFullName = trim((string) $candidateEntity->getFirstName());
                    }
                    $candidateEmail = (string) $candidateEntity->getEmail();
                }

                if ($candidateFullName === '') {
                    $candidateFullName = 'Unknown';
                }
                if ($candidateEmail === '') {
                    $candidateEmail = 'N/A';
                }

                $candidatesList[] = [
                    'registration_id' => $registration->getId(),
                    'name' => $candidateFullName,
                    'email' => $candidateEmail,
                    'registered_at' => $registration->getRegistered_at(),
                    'status' => $registration->getAttendance_status(),
                ];
            }

            $pendingActionsCount = 0;
            foreach ($candidatesList as $candidateRow) {
                $status = strtolower(trim((string) $candidateRow['status']));
                if (!in_array($status, ['confirmed', 'rejected'], true)) {
                    $pendingActionsCount += 1;
                }
            }

            $secondsUntilEvent = $eventDate->getTimestamp() - $now->getTimestamp();
            $isUrgent = $pendingActionsCount > 0 && $secondsUntilEvent >= 0 && $secondsUntilEvent <= (72 * 3600);

            if ($isUrgent) {
                $urgentNotifications[] = [
                    'title' => (string) $event->getTitle(),
                    'pending_count' => $pendingActionsCount,
                    'event_date' => $eventDate,
                ];
            }

            $eventsData[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'meta' => $eventDate->format('d M Y') . ' | ' . $event->getLocation(),
                'date' => $eventDate,
                'location' => $event->getLocation(),
                'capacity' => $event->getCapacity(),
                'event_type' => $event->getEvent_type(),
                'registrations' => $candidatesList,
                'registration_count' => count($candidatesList),
                'pending_actions_count' => $pendingActionsCount,
                'is_urgent' => $isUrgent,
            ];
        }

        return $this->render('front/modules/recruiter_event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'events' => $eventsData,
            'urgentNotifications' => $urgentNotifications,
        ]);
    }

    #[Route('/front/admin/event-registrations', name: 'admin_event_registrations')]
    public function adminEventRegistrations(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'admin') {
            $this->addFlash('warning', 'Only admins can view this registrations page.');
            return $this->redirectToRoute('front_events');
        }

        $events = $entityManager->getRepository(Recruitment_event::class)->findBy([], ['id' => 'DESC'], self::EVENT_LIST_LIMIT);

        $eventsData = [];
        foreach ($events as $event) {
            $eventDate = $event->getEvent_date();
            if (!$eventDate instanceof \DateTimeInterface) {
                continue;
            }

            $registrations = $event->getEvent_registrations();

            $candidatesList = [];
            foreach ($registrations as $registration) {
                $candidateEntity = $registration->getCandidate_id();
                $candidateFullName = '';
                $candidateEmail = '';

                if ($candidateEntity instanceof Candidate) {
                    $candidateFullName = trim(((string) $candidateEntity->getFirstName()) . ' ' . ((string) $candidateEntity->getLastName()));
                    if ($candidateFullName === '') {
                        $candidateFullName = trim((string) $candidateEntity->getFirstName());
                    }
                    $candidateEmail = (string) $candidateEntity->getEmail();
                }

                if ($candidateFullName === '') {
                    $candidateFullName = 'Unknown';
                }
                if ($candidateEmail === '') {
                    $candidateEmail = 'N/A';
                }

                $candidatesList[] = [
                    'registration_id' => $registration->getId(),
                    'name' => $candidateFullName,
                    'email' => $candidateEmail,
                    'registered_at' => $registration->getRegistered_at(),
                    'status' => $registration->getAttendance_status(),
                ];
            }

            $pendingActionsCount = 0;
            foreach ($candidatesList as $candidateRow) {
                $status = strtolower(trim((string) $candidateRow['status']));
                if (!in_array($status, ['confirmed', 'rejected'], true)) {
                    $pendingActionsCount += 1;
                }
            }

            $eventsData[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'meta' => $eventDate->format('d M Y') . ' | ' . $event->getLocation(),
                'date' => $eventDate,
                'location' => $event->getLocation(),
                'capacity' => $event->getCapacity(),
                'event_type' => $event->getEvent_type(),
                'registrations' => $candidatesList,
                'registration_count' => count($candidatesList),
                'pending_actions_count' => $pendingActionsCount,
                'is_urgent' => false,
            ];
        }

        return $this->render('front/modules/recruiter_event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'events' => $eventsData,
        ]);
    }

    #[Route('/front/recruiter/event-registrations/{id}/status', name: 'recruiter_update_registration_status', methods: ['POST'])]
    public function updateRegistrationStatus(
        Request $request,
        string $id,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can update registration status.');
            return $this->redirectToRoute('front_events');
        }

        $registration = $entityManager->getRepository(Event_registration::class)->find($id);
        if (!$registration) {
            throw $this->createNotFoundException('Registration not found');
        }

        $event = $registration->getEvent_id();
        $recruiter = $this->resolveCurrentRecruiter($request);
        if (!$recruiter instanceof Recruiter || $event->getRecruiter_id()->getId() !== $recruiter->getId()) {
            $this->addFlash('warning', 'You can only update registrations for your own events.');
            return $this->redirectToRoute('recruiter_event_registrations', ['role' => 'recruiter']);
        }

        $status = Event_registration::normalizeAttendanceStatus((string) $request->request->get('status'));
        if (in_array($status, [Event_registration::STATUS_CONFIRMED, Event_registration::STATUS_REJECTED], true)) {
            $registration->setAttendance_status($status);
            $entityManager->flush();

            $emailSent = $this->sendEventRegistrationStatusEmail($mailer, $logger, $registration, $status);
            $this->addFlash(
                $emailSent ? 'success' : 'warning',
                'Registration status updated to ' . ucfirst($status) . ($emailSent ? ' and the candidate was emailed.' : ', but the candidate email could not be sent.')
            );
        } else {
            $this->addFlash('warning', 'Invalid status provided.');
        }

        return $this->redirectToRoute('recruiter_event_registrations', ['role' => 'recruiter']);
    }

    private function sendEventRegistrationStatusEmail(
        MailerInterface $mailer,
        LoggerInterface $logger,
        Event_registration $registration,
        string $status
    ): bool {
        $candidate = $registration->getCandidate_id();
        $event = $registration->getEvent_id();
        if (!$candidate instanceof Candidate) {
            return false;
        }

        $recipient = trim((string) $candidate->getEmail());
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $logger->warning('Event registration status email skipped: invalid candidate email.', [
                'registration_id' => $registration->getId(),
                'candidate_id' => $candidate->getId(),
                'candidate_email' => $recipient,
            ]);

            return false;
        }

        $fromAddress = trim((string) ($_ENV['MAILER_FROM_ADDRESS'] ?? $_SERVER['MAILER_FROM_ADDRESS'] ?? $_ENV['MAILER_FROM'] ?? $_SERVER['MAILER_FROM'] ?? 'no-reply@talent-bridge.local'));
        $fromName = trim((string) ($_ENV['MAILER_FROM_NAME'] ?? $_SERVER['MAILER_FROM_NAME'] ?? 'Talent Bridge Recrutement'));
        $supportEmail = filter_var($fromAddress, FILTER_VALIDATE_EMAIL) ? $fromAddress : 'no-reply@talent-bridge.local';
        $statusLabel = $status === 'confirmed' ? 'Confirmed' : 'Rejected';
        $candidateName = $this->formatCandidateName($candidate);
        $eventTitle = trim((string) $event->getTitle());
        $eventType = trim((string) $event->getEvent_type());
        $eventDate = $event->getEvent_date() instanceof \DateTimeInterface
            ? $event->getEvent_date()->format('F j, Y \\a\\t H:i')
            : 'To be announced';
        $meetingLink = $status === 'confirmed' ? trim((string) $event->getMeet_link()) : '';
        $eventsUrl = $this->generateUrl('front_event_registrations', ['role' => 'candidate'], UrlGeneratorInterface::ABSOLUTE_URL);

        $eventTypeMessage = $status === 'confirmed'
            ? sprintf('Good news: your registration for "%s" has been accepted by the recruiter.', $eventTitle !== '' ? $eventTitle : 'this event')
            : sprintf('Your registration for "%s" was reviewed and was not accepted this time.', $eventTitle !== '' ? $eventTitle : 'this event');
        $closingLine = $status === 'confirmed'
            ? 'Your spot is confirmed. Please keep this email handy and check the event details before the scheduled date.'
            : 'You can keep exploring other hiring events on Talent Bridge and register for the ones that match your goals.';

        $qrCodeUrl = '';
        if ($status === 'confirmed') {
            $qrPayload = sprintf(
                'Talent Bridge Event Registration | Event: %s | Candidate: %s | Registration: %s',
                $eventTitle !== '' ? $eventTitle : (string) $event->getId(),
                $candidateName,
                (string) $registration->getId()
            );
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&format=png&data=' . rawurlencode($qrPayload);
        }

        $htmlBody = $this->renderView('emails/recruitment_event_status.html.twig', [
            'brandName' => 'Talent Bridge',
            'candidateName' => $candidateName,
            'eventTypeMessage' => $eventTypeMessage,
            'eventTitle' => $eventTitle !== '' ? $eventTitle : 'Recruitment event',
            'eventType' => $eventType !== '' ? $eventType : 'Event',
            'eventDate' => $eventDate,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'closingLine' => $closingLine,
            'qrCodeUrl' => $qrCodeUrl,
            'meetingLink' => $meetingLink,
            'eventsUrl' => $eventsUrl,
            'supportEmail' => $supportEmail,
        ]);

        $textBody = sprintf(
            "Hello %s,\n\n%s\n\nEvent: %s\nType: %s\nDate: %s\nStatus: %s\n\n%s\n\nView your registrations: %s",
            $candidateName,
            $eventTypeMessage,
            $eventTitle !== '' ? $eventTitle : 'Recruitment event',
            $eventType !== '' ? $eventType : 'Event',
            $eventDate,
            $statusLabel,
            $closingLine,
            $eventsUrl
        );

        try {
            $email = (new Email())
                ->from(new Address($fromAddress, $fromName !== '' ? $fromName : 'Talent Bridge'))
                ->to(new Address($recipient, $candidateName))
                ->subject(sprintf('Your event registration was %s', strtolower($statusLabel)))
                ->text($textBody)
                ->html($htmlBody);

            $mailer->send($email);

            return true;
        } catch (\Throwable $exception) {
            $logger->error('Event registration status email failed to send.', [
                'registration_id' => $registration->getId(),
                'candidate_id' => $candidate->getId(),
                'candidate_email' => $recipient,
                'event_id' => $event->getId(),
                'status' => $status,
                'error_message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function formatCandidateName(Candidate $candidate): string
    {
        $fullName = trim((string) $candidate->getFirstName() . ' ' . (string) $candidate->getLastName());

        return $fullName !== '' ? $fullName : 'Candidate';
    }

    #[Route('/front/interviews', name: 'front_interviews')]
    public function interviews(Request $request): Response
    {
        $role = $this->resolveSessionRole($request);

        $search = trim((string) $request->query->get('search', ''));
        $criteria = strtolower(trim((string) $request->query->get('criteria', 'all')));
        $sort = strtolower(trim((string) $request->query->get('sort', 'date_desc')));
        $allowedCriteria = ['all', 'title', 'meta', 'description', 'status'];
        $allowedSorts = ['default', 'date_desc', 'date_asc', 'status_asc', 'status_desc', 'title_asc', 'title_desc', 'meta_asc', 'meta_desc'];
        if (!in_array($criteria, $allowedCriteria, true)) {
            $criteria = 'all';
        }
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'date_desc';
        }

        $currentCandidate = $role === 'candidate' ? $this->resolveCurrentCandidate($request) : null;
        $currentRecruiter = $role === 'recruiter' ? $this->resolveCurrentRecruiter($request) : null;
        $interviewRepository = $this->doctrine->getRepository(Interview::class);
        if (($role === 'candidate' && !$currentCandidate instanceof Candidate) || ($role === 'recruiter' && !$currentRecruiter instanceof Recruiter)) {
            $interviews = [];
        } elseif ($interviewRepository instanceof InterviewRepository) {
            $interviews = $interviewRepository->findBySearchFilterSort($search, $criteria, $sort, $currentCandidate, $currentRecruiter);
        } else {
            $interviews = [];
        }

        $cards = [];
        foreach ($interviews as $interview) {
            try {
                $application = $interview->getApplication_id();
                $offer = $application->getOffer_id();

                $scheduledAt = $interview->getScheduled_at();
                $status = (string) $interview->getStatus();
                $title = (string) $offer->getTitle();
                $notes = trim((string) $interview->getNotes());
                $location = trim((string) $interview->getLocation());
                $meetingLink = trim((string) $interview->getMeeting_link());
                $mode = $this->normalizeInterviewMode((string) $interview->getMode(), $location, $meetingLink);
                $duration = (string) $interview->getDuration_minutes();
                $normalizedStatus = strtoupper(trim($status));
                if ($normalizedStatus === '') {
                    $normalizedStatus = 'SCHEDULED';
                }
                $latestFeedback = $this->findLatestInterviewFeedback($interview);
                $hasFeedback = $latestFeedback instanceof Interview_feedback;

                $displayStatus = 'Scheduled';
                $statusClass = 'bg-blue-lt';
                $statusKey = 'scheduled';
                if ($role === 'candidate') {
                    [$displayStatus, $statusClass, $statusKey] = $this->computeCandidateInterviewStatus($interview, $latestFeedback);
                } else {
                    [$displayStatus, $statusClass, $statusKey] = $this->computeRecruiterInterviewStatus($interview, $normalizedStatus, $latestFeedback);
                }

                $canModify = $role === 'recruiter' && $this->canModifyInterview($interview);
                $canFeedback = $role === 'recruiter' && $this->canSubmitFeedback($interview);
                $editUrl = $canModify
                    ? $this->generateUrl('front_interview_edit', ['id' => (string) $interview->getId(), 'role' => $role] + $request->query->all())
                    : '#';
                $deleteUrl = $canModify
                    ? $this->generateUrl('front_interview_delete', ['id' => (string) $interview->getId(), 'role' => $role] + $request->query->all())
                    : '#';
                $feedbackUrl = $canFeedback
                    ? $this->generateUrl('front_interview_feedback', ['id' => (string) $interview->getId(), 'role' => $role] + $request->query->all())
                    : '#';

                $detailExtra = [
                    'Date & Time: ' . $scheduledAt->format('d M Y H:i'),
                    'Duration: ' . $duration . ' min',
                    'Mode: ' . strtoupper($mode),
                ];
                if ($mode === 'onsite') {
                    $detailExtra[] = 'Location: ' . ($location === '' ? 'N/A' : $location);
                } else {
                    $detailExtra[] = 'Meeting Link: ' . ($meetingLink === '' ? 'N/A' : $meetingLink);
                }
                $detailExtra[] = 'Status: ' . $displayStatus;

                $cards[] = [
                    'id' => (string) $interview->getId(),
                    'application_id' => (string) $application->getId(),
                    'meta' => sprintf('%s | %s', $scheduledAt->format('d M Y | H:i'), $displayStatus),
                    'title' => sprintf('Interview: %s', $title === '' ? 'Untitled offer' : $title),
                    'text' => $notes === '' ? 'No interview notes available yet.' : substr($notes, 0, 190),
                    'scheduled_ts' => (string) $scheduledAt->getTimestamp(),
                    'full_notes' => $notes,
                    'form_scheduled_at' => $scheduledAt->format('Y-m-d\TH:i'),
                    'form_duration_minutes' => $duration,
                    'form_mode' => $mode,
                    'form_meeting_link' => $meetingLink,
                    'form_location' => $location,
                    'detail_extra' => $detailExtra,
                    'status_label' => $displayStatus,
                    'status_class' => $statusClass,
                    'status_key' => $statusKey,
                    'status_sort' => strtolower($displayStatus),
                    'review_score' => $hasFeedback ? (string) $latestFeedback->getOverall_score() : '80',
                    'review_decision' => $hasFeedback ? (string) $latestFeedback->getDecision() : 'accepted',
                    'review_comment' => $hasFeedback ? (string) $latestFeedback->getComment() : '',
                    'can_modify' => $canModify,
                    'can_feedback' => $canFeedback,
                    'review_label' => $hasFeedback ? 'Update Review' : 'Create Review',
                    'edit_url' => $editUrl,
                    'delete_url' => $deleteUrl,
                    'feedback_url' => $feedbackUrl,
                ];
            } catch (Throwable) {
                // Skip malformed rows so one broken interview does not break the page.
                continue;
            }
        }

        $upcomingInterviews = $this->interviewCalendarService->buildUpcomingFromCards($cards);

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
            'upcomingInterviews' => $upcomingInterviews,
            'listFilters' => [
                'search' => $search,
                'criteria' => $criteria,
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/front/job-applications/{applicationId}/status/{status}', name: 'front_application_set_status', methods: ['POST'])]
    public function setApplicationStatus(string $applicationId, string $status, Request $request): RedirectResponse
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can update application status.');
            return $this->redirectToRoute('front_job_applications', $request->query->all());
        }

        $normalizedStatus = $this->normalizeApplicationStatus($status);
        if ($normalizedStatus === null) {
            $this->addFlash('warning', 'Invalid status selected.');
            return $this->redirectToRoute('front_job_applications', $request->query->all());
        }

        $application = $this->doctrine->getRepository(Job_application::class)->find($applicationId);
        if (!$application instanceof Job_application) {
            $this->addFlash('warning', 'Application not found.');
            return $this->redirectToRoute('front_job_applications', $request->query->all());
        }

        $application->setCurrent_status($normalizedStatus);
        $this->doctrine->getManager()->flush();
        $this->addFlash('success', 'Application status updated.');

        return $this->redirectToRoute('front_job_applications', $request->query->all());
    }

    #[Route('/front/job-applications/{applicationId}/interview-availability', name: 'front_application_interview_availability', methods: ['GET'])]
    public function applicationInterviewAvailability(string $applicationId, Request $request): JsonResponse
    {
        $role = $this->resolveSessionRole($request);
        $application = $this->doctrine->getRepository(Job_application::class)->find($applicationId);
        if (!$application instanceof Job_application) {
            return new JsonResponse(['ok' => false, 'error' => 'Application not found.'], 404);
        }

        if ($role !== 'recruiter') {
            return new JsonResponse([
                'ok' => true,
                'canCreateInterview' => false,
                'createUrl' => '#',
                'reason' => 'Only recruiters can create interviews.',
            ]);
        }

        $hasActiveInterview = $this->hasActiveInterviewForApplication($application);
        $createUrl = $this->generateUrl('front_interview_create', ['applicationId' => $applicationId, 'role' => $role] + $request->query->all());

        return new JsonResponse([
            'ok' => true,
            'canCreateInterview' => !$hasActiveInterview,
            'createUrl' => $hasActiveInterview ? '#' : $createUrl,
            'reason' => $hasActiveInterview
                ? 'Interview already created for this application.'
                : '',
        ]);
    }

    #[Route('/front/interviews/generate-meeting-link', name: 'front_interview_generate_meeting_link', methods: ['POST'])]
    public function generateInterviewMeetingLink(Request $request, JitsiMeetingLinkGenerator $jitsiMeetingLinkGenerator): JsonResponse
    {
        $role = (string) $request->query->get('role', 'candidate');
        if ($role !== 'recruiter') {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Only recruiters can generate meeting links.',
            ], 403);
        }

        $mode = strtolower(trim((string) $request->request->get('mode', 'online')));
        if ($mode !== 'online') {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Meeting links can only be generated for online interviews.',
            ], 400);
        }

        $applicationId = trim((string) $request->request->get('application_id', ''));
        $interviewId = trim((string) $request->request->get('interview_id', ''));

        $meetingLink = $jitsiMeetingLinkGenerator->generate(
            $applicationId !== '' ? $applicationId : null,
            $interviewId !== '' ? $interviewId : null,
        );

        return new JsonResponse([
            'ok' => true,
            'meetingLink' => $meetingLink,
        ]);
    }

    #[Route('/front/interviews/create/{applicationId}', name: 'front_interview_create', methods: ['GET', 'POST'])]
    public function createInterview(string $applicationId, Request $request, LoggerInterface $logger): Response
    {
        $role = $this->resolveSessionRole($request);
        $application = $this->doctrine->getRepository(Job_application::class)->find($applicationId);
        if (!$application instanceof Job_application) {
            throw $this->createNotFoundException('Application not found.');
        }

        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can schedule interviews.');
            return $this->redirectToRoute('front_job_applications', $request->query->all());
        }

        $currentRecruiter = $this->resolveCurrentRecruiter($request);
        $offer = $application->getOffer_id();
        $recruiter = $offer->getRecruiter_id();
        if (!$currentRecruiter instanceof Recruiter || (string) $currentRecruiter->getId() !== (string) $recruiter->getId()) {
            $this->addFlash('warning', 'You can only schedule interviews for your own job offers.');
            return $this->redirectToRoute('front_job_applications', $request->query->all());
        }

        $formData = [
            'scheduled_at' => '',
            'duration_minutes' => '60',
            'mode' => 'online',
            'meeting_link' => '',
            'location' => '',
            'notes' => '',
        ];

        if ($request->isMethod('POST')) {
            $formData = [
                'scheduled_at' => (string) $request->request->get('scheduled_at', ''),
                'duration_minutes' => (string) $request->request->get('duration_minutes', '60'),
                'mode' => (string) $request->request->get('mode', 'online'),
                'meeting_link' => trim((string) $request->request->get('meeting_link', '')),
                'location' => trim((string) $request->request->get('location', '')),
                'notes' => trim((string) $request->request->get('notes', '')),
            ];

            if ($this->hasActiveInterviewForApplication($application)) {
                $this->addFlash('warning', 'This application already has an interview. Creating another one is not allowed.');
                return $this->redirectToRoute('front_job_applications', $request->query->all() + ['openCreateFor' => $applicationId]);
            }

            $validation = Interview::validateInput($formData, self::MAX_FUTURE_DAYS);
            if ($validation['ok']) {
                $interview = new Interview();
                $interview->setId($this->nextNumericId(Interview::class));
                $interview->setApplication_id($application);
                $interview->setRecruiter_id($recruiter);
                $interview->setScheduled_at($validation['scheduledAt']);
                $interview->setDuration_minutes($validation['duration']);
                $interview->setMode($validation['mode']);
                $interview->setMeeting_link($validation['meetingLink']);
                $interview->setLocation($validation['location']);
                $interview->setNotes($validation['notes']);
                $interview->setStatus('SCHEDULED');
                $interview->setCreated_at(new \DateTime());
                $interview->setReminder_sent(false);

                try {
                    $entityManager = $this->doctrine->getManager();
                    $entityManager->persist($interview);

                    $oldStatus = strtoupper(trim((string) $application->getCurrent_status()));
                    $application->setCurrent_status('INTERVIEW');

                    $history = new Application_status_history();
                    $history->setApplication_id($application);
                    $history->setStatus('INTERVIEW');
                    $history->setChanged_at(new \DateTime());
                    $history->setChanged_by($recruiter);
                    $history->setNote($oldStatus === 'INTERVIEW'
                        ? 'Interview scheduled while the application remains in interview stage.'
                        : 'Interview scheduled; application moved to Interview stage.'
                    );
                    $entityManager->persist($history);

                    $entityManager->flush();

                    $this->addFlash('success', 'Interview created successfully.');
                    return $this->redirectToRoute('front_interviews', $request->query->all());
                } catch (Throwable $exception) {
                    $logger->error('Interview creation failed.', [
                        'application_id' => $applicationId,
                        'recruiter_id' => (string) $recruiter->getId(),
                        'error_message' => $exception->getMessage(),
                    ]);

                    $this->addFlash('warning', 'Could not create interview. Please check the information and try again.');
                    return $this->redirectToRoute('front_job_applications', $request->query->all() + ['openCreateFor' => $applicationId]);
                }
            }

            $this->addFlash('warning', (string) $validation['error']);
            return $this->redirectToRoute('front_job_applications', $request->query->all() + ['openCreateFor' => $applicationId]);
        }

        return $this->render('front/modules/interview_form.html.twig', [
            'authUser' => ['role' => $role],
            'mode' => 'create',
            'applicationId' => $applicationId,
            'formData' => $formData,
        ]);
    }

    #[Route('/front/interviews/{id}/edit', name: 'front_interview_edit', methods: ['GET', 'POST'])]
    public function editInterview(string $id, Request $request): Response
    {
        $role = $this->resolveSessionRole($request);
        $interview = $this->doctrine->getRepository(Interview::class)->find($id);
        if (!$interview instanceof Interview) {
            throw $this->createNotFoundException('Interview not found.');
        }

        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can edit interviews.');
            return $this->redirectToRoute('front_interviews', $request->query->all());
        }

        if (!$this->canModifyInterview($interview)) {
            $this->addFlash('warning', 'Interview can no longer be modified (past or too close).');
            return $this->redirectToRoute('front_interviews', $request->query->all());
        }

        $formData = [
            'scheduled_at' => $interview->getScheduled_at()->format('Y-m-d\TH:i'),
            'duration_minutes' => (string) $interview->getDuration_minutes(),
            'mode' => (string) $interview->getMode(),
            'meeting_link' => (string) $interview->getMeeting_link(),
            'location' => (string) $interview->getLocation(),
            'notes' => (string) $interview->getNotes(),
        ];

        if ($request->isMethod('POST')) {
            $formData = [
                'scheduled_at' => (string) $request->request->get('scheduled_at', ''),
                'duration_minutes' => (string) $request->request->get('duration_minutes', '60'),
                'mode' => (string) $request->request->get('mode', 'online'),
                'meeting_link' => trim((string) $request->request->get('meeting_link', '')),
                'location' => trim((string) $request->request->get('location', '')),
                'notes' => trim((string) $request->request->get('notes', '')),
            ];

            $validation = Interview::validateInput($formData, self::MAX_FUTURE_DAYS);
            if ($validation['ok']) {
                $previousScheduledAt = $interview->getScheduled_at();
                $interview->setScheduled_at($validation['scheduledAt']);
                $interview->setDuration_minutes($validation['duration']);
                $interview->setMode($validation['mode']);
                $interview->setMeeting_link($validation['meetingLink']);
                $interview->setLocation($validation['location']);
                $interview->setNotes($validation['notes']);

                if ($previousScheduledAt->format('Y-m-d H:i:s') !== $validation['scheduledAt']->format('Y-m-d H:i:s')) {
                    $interview->setReminder_sent(false);
                }

                $this->doctrine->getManager()->flush();

                $this->addFlash('success', 'Interview updated successfully.');
                return $this->redirectToRoute('front_interviews', $request->query->all());
            }

            $this->addFlash('warning', (string) $validation['error']);
            return $this->redirectToRoute('front_interviews', $request->query->all() + ['openEditFor' => $id]);
        }

        return $this->render('front/modules/interview_form.html.twig', [
            'authUser' => ['role' => $role],
            'mode' => 'edit',
            'interviewId' => $id,
            'applicationId' => (string) $interview->getApplication_id()->getId(),
            'formData' => $formData,
        ]);
    }

    #[Route('/front/interviews/{id}/delete', name: 'front_interview_delete', methods: ['POST'])]
    public function deleteInterview(string $id, Request $request): RedirectResponse
    {
        $role = $this->resolveSessionRole($request);
        $interview = $this->doctrine->getRepository(Interview::class)->find($id);
        if (!$interview instanceof Interview) {
            $this->addFlash('warning', 'Interview not found.');
            return $this->redirectToRoute('front_interviews', $request->query->all());
        }

        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can delete interviews.');
            return $this->redirectToRoute('front_interviews', $request->query->all());
        }

        if (!$this->canModifyInterview($interview)) {
            $this->addFlash('warning', 'Interview can no longer be deleted (past or too close).');
            return $this->redirectToRoute('front_interviews', $request->query->all());
        }

        $application = $interview->getApplication_id();
        $entityManager = $this->doctrine->getManager();
        $entityManager->remove($interview);
        $entityManager->flush();

        if (!$this->hasActiveInterviewForApplication($application) && strtoupper((string) $application->getCurrent_status()) === 'INTERVIEW') {
            $application->setCurrent_status('IN_REVIEW');
            $entityManager->flush();
        }

        $this->addFlash('success', 'Interview deleted successfully.');

        return $this->redirectToRoute('front_interviews', $request->query->all());
    }

    #[Route('/front/interviews/{id}/feedback', name: 'front_interview_feedback', methods: ['GET', 'POST'])]
    public function feedbackInterview(string $id, Request $request): Response
    {
        $role = $this->resolveSessionRole($request);
        $interview = $this->doctrine->getRepository(Interview::class)->find($id);
        if (!$interview instanceof Interview) {
            throw $this->createNotFoundException('Interview not found.');
        }

        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can submit feedback.');
            return $this->redirectToRoute('front_interviews', $request->query->all());
        }

        if (!$this->canSubmitFeedback($interview)) {
            $this->addFlash('warning', 'Feedback can only be submitted after interview end time.');
            return $this->redirectToRoute('front_interviews', $request->query->all());
        }

        $existingFeedback = $this->doctrine->getRepository(Interview_feedback::class)->findBy(['interview_id' => $interview], ['created_at' => 'DESC'], 1);
        $feedback = $existingFeedback[0] ?? null;

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('front_interviews', $request->query->all() + ['openReviewFor' => $id]);
        }

        $formData = [
            'overall_score' => (string) $request->request->get('overall_score', '80'),
            'decision' => (string) $request->request->get('decision', 'accepted'),
            'comment' => trim((string) $request->request->get('comment', '')),
        ];

        $score = (int) $formData['overall_score'];
        $decision = $formData['decision'];
        $comment = $formData['comment'];

        if ($score < 0 || $score > 100) {
            $this->addFlash('warning', 'Score must be between 0 and 100.');
            return $this->redirectToRoute('front_interviews', $request->query->all() + ['openReviewFor' => $id]);
        }
        if (!in_array($decision, ['accepted', 'rejected'], true)) {
            $this->addFlash('warning', 'Decision must be accepted or rejected.');
            return $this->redirectToRoute('front_interviews', $request->query->all() + ['openReviewFor' => $id]);
        }
        if ($comment === '') {
            $this->addFlash('warning', 'Comment is required.');
            return $this->redirectToRoute('front_interviews', $request->query->all() + ['openReviewFor' => $id]);
        }

        $commentValidation = $this->validateReviewComment($comment);
        if (!$commentValidation['ok']) {
            $this->addFlash('warning', (string) $commentValidation['error']);
            return $this->redirectToRoute('front_interviews', $request->query->all() + ['openReviewFor' => $id]);
        }

        $entityManager = $this->doctrine->getManager();
        if (!$feedback instanceof Interview_feedback) {
            $feedback = new Interview_feedback();
            $feedback->setId($this->nextNumericId(Interview_feedback::class));
            $feedback->setInterview_id($interview);
            $feedback->setRecruiter_id($interview->getRecruiter_id());
            $entityManager->persist($feedback);
        }

        $feedback->setOverall_score($score);
        $feedback->setDecision($decision);
        $feedback->setComment((string) $commentValidation['value']);
        $feedback->setCreated_at(new \DateTime());

        $interview->setStatus('DONE');
        $application = $interview->getApplication_id();
        $application->setCurrent_status($decision === 'accepted' ? 'HIRED' : 'REJECTED');

        $entityManager->flush();
        $this->addFlash('success', 'Interview review saved.');

        return $this->redirectToRoute('front_interviews', $request->query->all());
    }

    #[Route('/front/profile', name: 'front_profile')]
    public function profile(Request $request, UsersRepository $userRepo, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $role = $this->resolveSessionRole($request);
        $userId = $this->resolveCurrentUserId($request);
        $user = $userRepo->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Please log in to access your profile.');
            return $this->redirectToRoute('app_login');
        }

        $candidateSkills = [];
        if ($role === 'candidate') {
            $candidate = $this->resolveCurrentCandidate($request);

            if ($request->isMethod('POST') && $request->request->get('profile_action') === 'skill_add') {
                if (!$candidate instanceof Candidate) {
                    $this->addFlash('warning', 'Only candidates can manage skills.');
                    return $this->redirectToRoute('front_profile');
                }

                $skillName = trim((string) $request->request->get('skill_name', ''));
                $skillLevel = trim((string) $request->request->get('skill_level', ''));
                $allowedLevels = ['beginner', 'intermediate', 'advanced'];

                if ($skillName === '') {
                    $this->addFlash('warning', 'Skill name is required.');
                } elseif (mb_strlen($skillName) > 100) {
                    $this->addFlash('warning', 'Skill name must not exceed 100 characters.');
                } elseif (!in_array($skillLevel, $allowedLevels, true)) {
                    $this->addFlash('warning', 'Please select a valid skill level.');
                } else {
                    $skill = new Candidate_skill();
                    $skill->setSkillName($skillName);
                    $skill->setLevel($skillLevel);
                    $skill->setCandidate($candidate);

                    $entityManager->persist($skill);
                    $entityManager->flush();
                    $this->addFlash('success', 'Skill added successfully.');
                }

                return $this->redirectToRoute('front_profile');
            }

            if ($request->isMethod('POST') && $request->request->get('profile_action') === 'skill_delete') {
                if (!$candidate instanceof Candidate) {
                    $this->addFlash('warning', 'Only candidates can manage skills.');
                    return $this->redirectToRoute('front_profile');
                }

                $skillId = (int) $request->request->get('skill_id', 0);
                $skill = $entityManager->getRepository(Candidate_skill::class)->find($skillId);

                if (!$skill instanceof Candidate_skill || !$skill->getCandidate() instanceof Candidate || (string) $skill->getCandidate()->getId() !== (string) $candidate->getId()) {
                    $this->addFlash('warning', 'Skill not found or not allowed.');
                } else {
                    $entityManager->remove($skill);
                    $entityManager->flush();
                    $this->addFlash('success', 'Skill removed successfully.');
                }

                return $this->redirectToRoute('front_profile');
            }

            if ($request->isMethod('POST') && $request->request->get('profile_action') === 'skill_update') {
                if (!$candidate instanceof Candidate) {
                    $this->addFlash('warning', 'Only candidates can manage skills.');
                    return $this->redirectToRoute('front_profile');
                }

                $skillId = (int) $request->request->get('skill_id', 0);
                $skillName = trim((string) $request->request->get('skill_name', ''));
                $skillLevel = trim((string) $request->request->get('skill_level', ''));
                $allowedLevels = ['beginner', 'intermediate', 'advanced'];

                $skill = $entityManager->getRepository(Candidate_skill::class)->find($skillId);
                if (!$skill instanceof Candidate_skill || !$skill->getCandidate() instanceof Candidate || (string) $skill->getCandidate()->getId() !== (string) $candidate->getId()) {
                    $this->addFlash('warning', 'Skill not found or not allowed.');
                    return $this->redirectToRoute('front_profile');
                }

                if ($skillName === '') {
                    $this->addFlash('warning', 'Skill name is required.');
                    return $this->redirectToRoute('front_profile');
                }

                if (mb_strlen($skillName) > 100) {
                    $this->addFlash('warning', 'Skill name must not exceed 100 characters.');
                    return $this->redirectToRoute('front_profile');
                }

                if (!in_array($skillLevel, $allowedLevels, true)) {
                    $this->addFlash('warning', 'Please select a valid skill level.');
                    return $this->redirectToRoute('front_profile');
                }

                $skill->setSkillName($skillName);
                $skill->setLevel($skillLevel);
                $entityManager->flush();

                $this->addFlash('success', 'Skill updated successfully.');
                return $this->redirectToRoute('front_profile');
            }

            if ($candidate instanceof Candidate) {
                $candidateSkills = $entityManager->getRepository(Candidate_skill::class)->findBy([
                    'candidate' => $candidate,
                ], ['id' => 'DESC']);
            }
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = trim((string) $user->getPlainPassword());
            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $user->setPlainPassword(null);
            if ($user instanceof Candidate) {
                $coords = $this->geolocationService->tryGeocode((string) $user->getLocation());
                if ($coords !== null) {
                    $user->setLatitude($coords['lat']);
                    $user->setLongitude($coords['lng']);
                }
            }

            $entityManager->flush();
            $request->getSession()->set('user_name', $user->getFirstName());

            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('front_profile');
        }

        return $this->render('front/profile.html.twig', [
            'form' => $form->createView(),
            'authUser' => ['role' => $role],
            'profileUser' => $user,
            'candidateSkills' => $candidateSkills,
        ]);
    }

    private function resolveCurrentUserId(Request $request): string
    {
        $user = $this->getUser();
        if ($user instanceof Users) {
            return (string) $user->getId();
        }

        return (string) $request->getSession()->get('user_id', '');
    }

    private function resolveSessionRole(Request $request): string
    {
        $roles = [];
        $user = $this->getUser();
        if ($user instanceof Users) {
            $roles = $user->getRoles();
        }

        if ($roles === []) {
            $roles = (array) $request->getSession()->get('user_roles', []);
        }

        if (in_array('ROLE_RECRUITER', $roles, true)) {
            return 'recruiter';
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'admin';
        }

        return 'candidate';
    }

    private function resolveCurrentCandidate(Request $request): ?Candidate
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === '') {
            return null;
        }

        $candidate = $this->doctrine->getRepository(Candidate::class)->find($userId);
        return $candidate instanceof Candidate ? $candidate : null;
    }

    private function resolveCurrentRecruiter(Request $request): ?Recruiter
    {
        $recruiterId = $this->resolveCurrentRecruiterId($request);
        if ($recruiterId === '') {
            return null;
        }

        $recruiter = $this->doctrine->getRepository(Recruiter::class)->find($recruiterId);
        return $recruiter instanceof Recruiter ? $recruiter : null;
    }

    private function resolveCurrentRecruiterId(Request $request): string
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === '') {
            return '';
        }

        $recruiterById = $this->doctrine->getRepository(Recruiter::class)->find($userId);
        if ($recruiterById instanceof Recruiter) {
            return (string) $recruiterById->getId();
        }

        try {
            $entityManager = $this->doctrine->getManager();
            if (!$entityManager instanceof EntityManagerInterface) {
                return $userId;
            }

            $legacyRecruiterId = $entityManager->getConnection()
                ->fetchOne(
                    'SELECT id FROM recruiter WHERE user_id = :user_id LIMIT 1',
                    ['user_id' => $userId]
                );
            if ($legacyRecruiterId !== false && $legacyRecruiterId !== null && (string) $legacyRecruiterId !== '') {
                return (string) $legacyRecruiterId;
            }
        } catch (\Throwable) {
            // Keep fallback behavior when legacy column is absent.
        }

        return $userId;
    }

    /**
     * @return Pagerfanta<mixed>
     */
    private function createPagerFromQueryBuilder(QueryBuilder $queryBuilder, int $page, int $perPage): Pagerfanta
    {
        $pager = new Pagerfanta(new QueryAdapter($queryBuilder, true, false));
        $pager->setMaxPerPage($perPage);
        try {
            $pager->setCurrentPage($page);
        } catch (\Throwable) {
            $pager->setCurrentPage(1);
        }

        return $pager;
    }

    /**
     * @param array<int, mixed> $items
     * @return Pagerfanta<mixed>
     */
    private function createPagerFromArray(array $items, int $page, int $perPage): Pagerfanta
    {
        $pager = new Pagerfanta(new ArrayAdapter($items));
        $pager->setMaxPerPage($perPage);
        try {
            $pager->setCurrentPage($page);
        } catch (\Throwable) {
            $pager->setCurrentPage(1);
        }

        return $pager;
    }

    /**
     * @param Pagerfanta<mixed> $pager
     * @param array<string, mixed> $routeParamsBase
     *
     * @return array<string, mixed>
     */
    private function buildPaginationView(Pagerfanta $pager, array $routeParamsBase): array
    {
        $currentPage = $pager->getCurrentPage();
        $totalPages = max(1, $pager->getNbPages());

        $pages = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $pages[] = $i;
        }

        return [
            'enabled' => $totalPages > 1,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'has_previous' => $pager->hasPreviousPage(),
            'has_next' => $pager->hasNextPage(),
            'previous_page' => $pager->hasPreviousPage() ? $currentPage - 1 : 1,
            'next_page' => $pager->hasNextPage() ? $currentPage + 1 : $totalPages,
            'pages' => $pages,
            'route_params_base' => $routeParamsBase,
        ];
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function resolveLocationCoordinates(string $location): array
    {
        $coords = $this->geolocationService->tryGeocode($location);

        if ($coords === null) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
        ];
    }
    /**
     * @return array{ok: false, error: string}|array{ok: true, value: string}
     */
    private function validateReviewComment(string $comment): array
    {
        $trimmed = trim($comment);
        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'Comment is required.'];
        }

        if (!preg_match(self::REVIEW_COMMENT_REGEX, $trimmed)) {
            return ['ok' => false, 'error' => 'Comment must be 10-1000 chars and use letters, numbers or common punctuation.'];
        }

        return ['ok' => true, 'value' => $trimmed];
    }

    private function canModifyInterview(Interview $interview): bool
    {
        try {
            $startAt = $this->toImmutableDateTime($interview->getScheduled_at());
            if (!$startAt instanceof \DateTimeImmutable) {
                return false;
            }

            $lockTime = $startAt->modify('-' . self::EDIT_LOCK_HOURS . ' hours');
            return new \DateTimeImmutable() < $lockTime;
        } catch (Throwable) {
            return false;
        }
    }

    #[Route('/front/job-offers/comments/analyze', name: 'front_job_offer_comment_analyze', methods: ['POST'])]
    public function analyzeJobOfferComment(Request $request): JsonResponse
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'admin') {
            return $this->json([
                'ok' => false,
                'error' => 'Only admins can analyze comments from this page.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $comment = trim((string) ($payload['comment'] ?? ''));
        $validation = Job_offer_comment::validateCommentText($comment);
        if ($validation['ok'] !== true) {
            return $this->json([
                'ok' => false,
                'error' => $validation['error'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $analysis = $this->commentAnalyzerService->analyze((string) $validation['value']);

        return $this->json([
            'ok' => true,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/front/job-offers/{id}/comments', name: 'front_job_offer_comment_create', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function createJobOfferComment(string $id, Request $request, Connection $connection): JsonResponse
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'candidate') {
            return $this->json([
                'ok' => false,
                'error' => 'Only candidates can post comments.',
            ], Response::HTTP_FORBIDDEN);
        }

        $candidate = $this->resolveCurrentCandidate($request);
        if (!$candidate instanceof Candidate) {
            return $this->json([
                'ok' => false,
                'error' => 'Candidate session is required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $offerExists = $connection->fetchOne('SELECT id FROM job_offer WHERE id = :id LIMIT 1', ['id' => $id]);
        if ($offerExists === false || $offerExists === null) {
            return $this->json([
                'ok' => false,
                'error' => 'Job offer not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $comment = trim((string) ($payload['comment'] ?? ''));
        $validation = Job_offer_comment::validateCommentText($comment);
        if ($validation['ok'] !== true) {
            return $this->json([
                'ok' => false,
                'error' => $validation['error'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $analysis = $this->commentAnalyzerService->analyze((string) $validation['value']);
        $labelsJson = '[]';
        try {
            $labelsJson = (string) json_encode((array) ($analysis['labels'] ?? []), JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $labelsJson = '[]';
        }

        $now = new \DateTimeImmutable();
        $commentId = (string) ((int) round(microtime(true) * 1000) . random_int(100, 999));
        $moderationStatus = (($analysis['flagged'] ?? false) === true)
            ? Job_offer_comment::STATUS_FLAGGED
            : Job_offer_comment::STATUS_APPROVED;
        $visibilityStatus = (($analysis['autoHidden'] ?? false) === true)
            ? Job_offer_comment::VISIBILITY_HIDDEN
            : Job_offer_comment::VISIBILITY_VISIBLE;

        try {
            $connection->insert('job_offer_comment', [
                'id' => $commentId,
                'job_offer_id' => $id,
                'candidate_id' => (string) $candidate->getId(),
                'comment_text' => (string) $validation['value'],
                'toxicity_score' => (float) ($analysis['toxicityScore'] ?? 0),
                'spam_score' => (float) ($analysis['spamScore'] ?? 0),
                'sentiment' => (string) ($analysis['sentiment'] ?? 'neutral'),
                'labels' => $labelsJson,
                'moderation_status' => $moderationStatus,
                'visibility_status' => $visibilityStatus,
                'is_auto_flagged' => (($analysis['flagged'] ?? false) === true) ? 1 : 0,
                'analyzer_source' => (string) ($analysis['provider'] ?? 'heuristic'),
                'created_at' => $now->format('Y-m-d H:i:s'),
                'analyzed_at' => $now->format('Y-m-d H:i:s'),
                'moderated_at' => null,
                'moderator_id' => null,
                'moderator_action_note' => null,
            ]);
        } catch (\Throwable) {
            return $this->json([
                'ok' => false,
                'error' => 'Unable to save comment right now.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'ok' => true,
            'comment' => [
                'id' => $commentId,
                'text' => (string) $validation['value'],
                'status' => $moderationStatus,
                'visibility' => $visibilityStatus,
                'createdAt' => $now->format(DATE_ATOM),
            ],
            'analysis' => $analysis,
        ]);
    }

    #[Route('/front/job-offers/{id}/comments', name: 'front_job_offer_comments_list', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function listJobOfferComments(string $id, Request $request, Connection $connection): JsonResponse
    {
        $role = $this->resolveSessionRole($request);
        $candidate = $this->resolveCurrentCandidate($request);
        $params = ['offer_id' => $id, 'visible_status' => Job_offer_comment::VISIBILITY_VISIBLE];
        $sql = <<<'SQL'
SELECT c.id, c.comment_text, c.sentiment, c.moderation_status, c.visibility_status, c.created_at,
       u.first_name, u.last_name
FROM job_offer_comment c
LEFT JOIN users u ON u.id = c.candidate_id
WHERE c.job_offer_id = :offer_id
  AND c.visibility_status = :visible_status
ORDER BY c.created_at DESC
LIMIT 25
SQL;

        if ($role === 'admin') {
            $params = ['offer_id' => $id];
            $sql = <<<'SQL'
SELECT c.id, c.comment_text, c.sentiment, c.moderation_status, c.visibility_status, c.created_at,
       u.first_name, u.last_name
FROM job_offer_comment c
LEFT JOIN users u ON u.id = c.candidate_id
WHERE c.job_offer_id = :offer_id
ORDER BY c.created_at DESC
LIMIT 25
SQL;
        } elseif ($candidate instanceof Candidate) {
            $sql = <<<'SQL'
SELECT c.id, c.comment_text, c.sentiment, c.moderation_status, c.visibility_status, c.created_at,
       u.first_name, u.last_name
FROM job_offer_comment c
LEFT JOIN users u ON u.id = c.candidate_id
WHERE c.job_offer_id = :offer_id
  AND (c.visibility_status = :visible_status OR c.candidate_id = :candidate_id)
ORDER BY c.created_at DESC
LIMIT 25
SQL;
            $params['candidate_id'] = (string) $candidate->getId();
        }

        try {
            $rows = $connection->fetchAllAssociative($sql, $params);
        } catch (\Throwable) {
            return $this->json([
                'ok' => false,
                'error' => 'Unable to load comments right now.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $comments = array_map(function (array $row): array {
            $authorName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            if ($authorName === '') {
                $authorName = 'Candidate';
            }

            return [
                'id' => (string) ($row['id'] ?? ''),
                'text' => (string) ($row['comment_text'] ?? ''),
                'sentiment' => (string) ($row['sentiment'] ?? 'neutral'),
                'status' => (string) ($row['moderation_status'] ?? Job_offer_comment::STATUS_APPROVED),
                'visibility' => (string) ($row['visibility_status'] ?? Job_offer_comment::VISIBILITY_VISIBLE),
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'author' => $authorName,
            ];
        }, $rows);

        return $this->json([
            'ok' => true,
            'comments' => $comments,
        ]);
    }

    private function normalizeApplicationStatus(string $status): ?string
    {
        $normalized = strtoupper(trim($status));
        $aliases = [
            'SUBMITTED' => 'SUBMITTED',
            'UNDER_REVIEW' => 'IN_REVIEW',
            'IN_REVIEW' => 'IN_REVIEW',
            'SHORTLISTED' => 'SHORTLISTED',
            'DECLINED' => 'REJECTED',
            'REJECTED' => 'REJECTED',
            'INTERVIEW_SCHEDULED' => 'INTERVIEW',
            'INTERVIEW' => 'INTERVIEW',
            'ACCEPTED' => 'HIRED',
            'HIRED' => 'HIRED',
        ];

        return $aliases[$normalized] ?? null;
    }

    private function canSubmitFeedback(Interview $interview): bool
    {
        try {
            $startAt = $this->toImmutableDateTime($interview->getScheduled_at());
            if (!$startAt instanceof \DateTimeImmutable) {
                return false;
            }

            $endTime = $startAt->modify('+' . $interview->getDuration_minutes() . ' minutes');
            return new \DateTimeImmutable() >= $endTime;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function computeCandidateInterviewStatus(Interview $interview, ?Interview_feedback $latestFeedback = null): array
    {
        try {
            $now = new \DateTimeImmutable();
            $start = $this->toImmutableDateTime($interview->getScheduled_at());
            if (!$start instanceof \DateTimeImmutable) {
                return ['Pending', 'bg-blue-lt', 'pending'];
            }

            $end = $start->modify('+' . $interview->getDuration_minutes() . ' minutes');
            if (!$latestFeedback instanceof Interview_feedback) {
                $latestFeedback = $this->findLatestInterviewFeedback($interview);
            }

            if ($latestFeedback instanceof Interview_feedback) {
                $decision = strtolower((string) $latestFeedback->getDecision());
                if ($decision === 'accepted') {
                    return ['Accepted', 'bg-green-lt', 'accepted'];
                }

                if ($decision === 'rejected') {
                    return ['Rejected', 'bg-red-lt', 'rejected'];
                }
            }

            if ($now >= $end) {
                return ['Under Review', 'bg-orange-lt', 'pending'];
            }

            return ['Pending', 'bg-blue-lt', 'pending'];
        } catch (Throwable) {
        }

        return ['Pending', 'bg-blue-lt', 'pending'];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function computeRecruiterInterviewStatus(Interview $interview, string $normalizedStatus, ?Interview_feedback $latestFeedback = null): array
    {
        try {
            if (!$latestFeedback instanceof Interview_feedback) {
                $latestFeedback = $this->findLatestInterviewFeedback($interview);
            }

            if ($latestFeedback instanceof Interview_feedback) {
                $decision = strtolower((string) $latestFeedback->getDecision());
                if ($decision === 'accepted') {
                    return ['Accepted', 'bg-green-lt', 'accepted'];
                }

                if ($decision === 'rejected') {
                    return ['Rejected', 'bg-red-lt', 'rejected'];
                }
            }

            $startAt = $this->toImmutableDateTime($interview->getScheduled_at());
            if (!$startAt instanceof \DateTimeImmutable) {
                return ['Scheduled', 'bg-blue-lt', 'scheduled'];
            }

            $endTime = $startAt->modify('+' . $interview->getDuration_minutes() . ' minutes');
            if (new \DateTimeImmutable() >= $endTime) {
                return ['Pending', 'bg-orange-lt', 'pending'];
            }
        } catch (Throwable) {
        }

        if ($normalizedStatus === 'CANCELLED') {
            return ['Rejected', 'bg-red-lt', 'rejected'];
        }

        return ['Scheduled', 'bg-blue-lt', 'scheduled'];
    }

    private function findLatestInterviewFeedback(Interview $interview): ?Interview_feedback
    {
        $rows = $this->doctrine->getRepository(Interview_feedback::class)->findBy(['interview_id' => $interview], ['created_at' => 'DESC'], 1);
        $latest = $rows[0] ?? null;
        return $latest instanceof Interview_feedback ? $latest : null;
    }

    private function normalizeInterviewMode(?string $mode, ?string $location = null, ?string $meetingLink = null): string
    {
        $value = strtolower(trim((string) $mode));
        if (in_array($value, ['onsite', 'on_site', 'on-site', 'on site', 'in_person', 'in-person', 'in person'], true)) {
            return 'onsite';
        }

        if (in_array($value, ['online', 'on_line', 'on-line', 'on line'], true)) {
            return 'online';
        }

        $normalizedLocation = trim((string) $location);
        $normalizedMeetingLink = trim((string) $meetingLink);
        if ($normalizedLocation !== '' && $normalizedMeetingLink === '') {
            return 'onsite';
        }

        return 'online';
    }

    private function hasActiveInterviewForApplication(Job_application $application): bool
    {
        $existingInterview = $this->doctrine
            ->getRepository(Interview::class)
            ->findOneBy(['application_id' => $application]);

        return $existingInterview instanceof Interview;
    }

    private function toImmutableDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }

        return null;
    }

    /**
     * @param class-string<Event_registration|Interview|Interview_feedback> $entityClass
     */
    private function nextNumericId(string $entityClass): string
    {
        $last = match ($entityClass) {
            Event_registration::class => $this->doctrine->getRepository(Event_registration::class)->findBy([], ['id' => 'DESC'], 1),
            Interview::class => $this->doctrine->getRepository(Interview::class)->findBy([], ['id' => 'DESC'], 1),
            Interview_feedback::class => $this->doctrine->getRepository(Interview_feedback::class)->findBy([], ['id' => 'DESC'], 1),
            default => [],
        };

        if (empty($last)) {
            return '1';
        }

        $lastId = (int) $last[0]->getId();
        return (string) ($lastId + 1);
    }

    /**
     * @param array<array-key, mixed> $rawSkills
     * @return list<array{name: string, level: string}>
     */
    private function normalizeSkills(array $rawSkills): array
    {
        $skills = [];

        foreach ($rawSkills as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            $level = trim((string) ($entry['level'] ?? ''));
            if ($name === '' || !in_array($level, self::SKILL_LEVELS, true)) {
                continue;
            }

            $skills[] = ['name' => $name, 'level' => $level];
        }

        return $skills;
    }

    private function normalizeStoredContractType(string $rawContractType): string
    {
        $value = trim($rawContractType);
        if ($value === '') {
            return '';
        }

        if (in_array($value, self::CONTRACT_TYPES, true)) {
            return $value;
        }

        $normalized = strtoupper(str_replace(['-', ' '], '_', $value));

        return match ($normalized) {
            'CDI' => 'CDI',
            'CDD' => 'CDD',
            'STAGE', 'INTERNSHIP' => 'Internship',
            'FREELANCE' => 'Freelance',
            'FULL_TIME', 'FULLTIME' => 'Full-time',
            'PART_TIME', 'PARTTIME' => 'Part-time',
            'REMOTE', 'REMOTE_CONTRACT' => 'Remote Contract',
            default => '',
        };
    }

    private static function normalizeStoredSkillLevel(string $rawLevel): string
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', trim($rawLevel)));

        return match ($normalized) {
            'beginner', 'junior', 'entry', 'entry_level' => 'beginner',
            'intermediate', 'mid', 'middle', 'mid_level' => 'intermediate',
            'advanced', 'senior', 'expert' => 'advanced',
            default => in_array($normalized, self::SKILL_LEVELS, true) ? $normalized : '',
        };
    }

    /**
     * @param array<int, array{name: string, level: string}> $skills
     */
    private function formatSkillsForPrompt(array $skills): string
    {
        if ($skills === []) {
            return 'Not specified';
        }

        $parts = [];
        foreach ($skills as $skill) {
            $name = trim($skill['name']);
            $level = trim($skill['level']);
            if ($name === '') {
                continue;
            }

            $parts[] = $level !== '' ? sprintf('%s (%s)', $name, $level) : $name;
        }

        return $parts === [] ? 'Not specified' : implode(', ', $parts);
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

    /**
     * @return array{ok: true, text: string, model: string}|array{ok: false, error: string}
     */
    private function requestGroqGenerateJobOffer(HttpClientInterface $httpClient, string $apiKey, string $prompt): array
    {
        $lastError = 'Groq service is currently unavailable.';

        foreach (self::GROQ_JOB_OFFER_MODELS as $model) {
            try {
                $response = $httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are an expert HR assistant. Return only valid JSON.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => 0.35,
                        'max_tokens' => 900,
                        'response_format' => ['type' => 'json_object'],
                    ],
                    'timeout' => 25,
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->toArray(false);

                if ($statusCode >= 400) {
                    $lastError = $this->extractGroqErrorMessage($body, $lastError);
                    continue;
                }

                $text = trim((string) ($body['choices'][0]['message']['content'] ?? ''));
                if ($text === '') {
                    $lastError = 'Groq returned an empty response.';
                    continue;
                }

                return ['ok' => true, 'text' => $text, 'model' => $model];
            } catch (\Throwable) {
                $lastError = 'Failed to contact Groq.';
            }
        }

        return ['ok' => false, 'error' => $lastError];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function extractGroqErrorMessage(array $body, string $fallback): string
    {
        $message = trim((string) ($body['error']['message'] ?? ''));
        if ($message === '') {
            return $fallback;
        }

        return mb_strlen($message) > 180 ? mb_substr($message, 0, 180) . '...' : $message;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeAiJsonPayload(string $rawPayload): ?array
    {
        $payload = trim($rawPayload);
        if ($payload === '') {
            return null;
        }

        $payload = preg_replace('/^```(?:json)?\s*/i', '', $payload) ?? $payload;
        $payload = preg_replace('/\s*```$/', '', $payload) ?? $payload;
        $payload = trim($payload);

        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $firstBrace = strpos($payload, '{');
        $lastBrace = strrpos($payload, '}');
        if ($firstBrace === false || $lastBrace === false || $lastBrace <= $firstBrace) {
            return null;
        }

        $jsonChunk = substr($payload, $firstBrace, $lastBrace - $firstBrace + 1);
        $decodedChunk = json_decode($jsonChunk, true);

        return is_array($decodedChunk) ? $decodedChunk : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{title: string, description: string, contract_type: string, location: string, skills: list<array{name: string, level: string}>}
     */
    private function normalizeAiJobOfferPayload(array $payload, string $title): array
    {
        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            $description = 'We are hiring a ' . $title . ' to join our team and deliver high-impact work in a collaborative environment.';
        }

        if (strlen($description) > 1000) {
            $description = substr($description, 0, 1000);
        }

        $requiredSkills = $this->normalizeAiTextList($payload['required_skills'] ?? [], 8);
        if (count($requiredSkills) === 0) {
            $requiredSkills = [$title . ' fundamentals'];
        }

        $experienceLevel = strtolower(trim((string) ($payload['experience_level'] ?? 'mid')));
        $defaultLevel = 'intermediate';
        if ($experienceLevel === 'junior') {
            $defaultLevel = 'beginner';
        } elseif ($experienceLevel === 'senior') {
            $defaultLevel = 'advanced';
        }

        $skills = array_map(static function (string $name) use ($defaultLevel): array {
            return ['name' => $name, 'level' => $defaultLevel];
        }, $requiredSkills);

        $contractType = $this->mapAiContractType((string) ($payload['contract_type'] ?? ''));
        $location = trim((string) ($payload['suggested_location'] ?? ''));
        if ($location === '') {
            $location = 'Tunis';
        }

        return [
            'title' => $title,
            'description' => $description,
            'contract_type' => $contractType,
            'location' => $location,
            'skills' => $skills,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeAiTextList(mixed $value, int $maxItems = 8): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $rawItem) {
            $item = trim((string) $rawItem);
            if ($item === '') {
                continue;
            }

            $items[] = $item;
            if (count($items) >= $maxItems) {
                break;
            }
        }

        return array_values(array_unique($items));
    }

    private function mapAiContractType(string $rawContractType): string
    {
        $contractType = strtoupper(trim($rawContractType));
        if ($contractType === '') {
            return 'CDI';
        }

        return match ($contractType) {
            'CDI' => 'CDI',
            'CDD' => 'CDD',
            'STAGE', 'INTERNSHIP' => 'Internship',
            'FREELANCE' => 'Freelance',
            'FULL-TIME', 'FULL TIME', 'FULL_TIME' => 'Full-time',
            'PART-TIME', 'PART TIME' => 'Part-time',
            'REMOTE', 'REMOTE CONTRACT' => 'Remote Contract',
            default => in_array($rawContractType, self::CONTRACT_TYPES, true) ? $rawContractType : 'CDI',
        };
    }

}


