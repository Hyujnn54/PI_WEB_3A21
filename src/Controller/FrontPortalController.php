<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Candidate_skill;
use App\Entity\Event_registration;
use App\Entity\Interview;
use App\Entity\Interview_feedback;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use App\Form\ProfileType;
use App\Repository\UsersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/offermanagement')]
class FrontPortalController extends AbstractController
{
    private const MAX_FUTURE_DAYS = 90;
    private const EDIT_LOCK_HOURS = 2;
    private const LOCATION_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,120}$/u';
    private const TEXTAREA_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{0,1000}$/u';
    private const REVIEW_COMMENT_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,1000}$/u';
    private const CONTRACT_TYPES = ['CDI', 'CDD', 'Internship', 'Freelance', 'Part-time', 'Remote Contract'];
    private const SKILL_LEVELS = ['beginner', 'intermediate', 'advanced'];
    private const JOB_STATUSES = ['open', 'paused', 'closed'];

    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(Request $request, Connection $connection): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentUserId = $this->resolveCurrentUserId($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
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
                    if ($offer instanceof Job_offer) {
                        $appliedOfferIds[(string) $offer->getId()] = true;
                    }
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

            $rowsSql = 'SELECT id, recruiter_id, title, description, location, contract_type, status, deadline FROM job_offer';
            $rowsParams = [];
            if ($role === 'recruiter') {
                $rowsSql .= ' WHERE recruiter_id = :recruiter_id';
                $rowsParams['recruiter_id'] = $currentRecruiterId;
            }
            $rowsSql .= ' ORDER BY created_at DESC LIMIT 25';

            $rows = $connection->fetchAllAssociative($rowsSql, $rowsParams);

            $dbCards = array_map(function (array $row) use ($connection, $now, $appliedOfferIds, $currentRecruiterId): array {
                $formattedDeadline = '';
                $isExpired = false;
                try {
                    $deadlineAt = new \DateTimeImmutable((string) ($row['deadline'] ?? ''));
                    $formattedDeadline = $deadlineAt->format('Y-m-d');
                    $isExpired = $deadlineAt < $now;
                } catch (\Throwable $exception) {
                    $formattedDeadline = '';
                    $isExpired = false;
                }

                $skills = $connection->fetchAllAssociative(
                    'SELECT skill_name, level_required FROM offer_skill WHERE offer_id = :offer_id ORDER BY id ASC',
                    ['offer_id' => (string) $row['id']]
                );

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
                ];
            }, $rows);

            foreach ($dbCards as $dbCard) {
                if (($dbCard['is_expired'] ?? false) === true) {
                    $expiredOffers[] = $dbCard;
                }

                $cards[] = $dbCard;
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
                     WHERE w.recruiter_id = :recruiter_id AND w.status = :status
                     ORDER BY w.created_at DESC',
                    ['recruiter_id' => $currentRecruiterId, 'status' => 'SENT']
                );
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

        return $this->render('front/modules/job_offers.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
            'contractTypes' => self::CONTRACT_TYPES,
            'warnings' => $warnings,
            'expiredOffers' => $expiredOffers,
        ]);
    }

    #[Route('/front/job-offers/statistics', name: 'front_job_offers_statistics')]
    public function jobOffersStatistics(Request $request, Connection $connection): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can access offer statistics.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        $rows = [];
        $offerStats = $this->buildOfferStats([]);

        try {
            $rows = $connection->fetchAllAssociative(
                'SELECT id, recruiter_id, title, location, contract_type, status, deadline FROM job_offer WHERE recruiter_id = :recruiter_id ORDER BY created_at DESC LIMIT 50',
                ['recruiter_id' => $currentRecruiterId]
            );
            $offerStats = $this->buildOfferStats($rows);
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to load offer statistics right now.');
        }

        return $this->render('front/modules/job_offer_statistics.html.twig', [
            'authUser' => ['role' => $role],
            'offerStats' => $offerStats,
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

            if ($formData['title'] === '') {
                $fieldErrors['title'] = 'Title is required.';
            }
            if ($formData['contract_type'] === '') {
                $fieldErrors['contract_type'] = 'Contract type is required.';
            } elseif (!in_array($formData['contract_type'], self::CONTRACT_TYPES, true)) {
                $fieldErrors['contract_type'] = 'Please select a valid contract type.';
            }
            if ($formData['status'] === '') {
                $fieldErrors['status'] = 'Status is required.';
            } elseif (!in_array($formData['status'], self::JOB_STATUSES, true)) {
                $fieldErrors['status'] = 'Please select a valid status.';
            }
            if ($formData['description'] === '') {
                $fieldErrors['description'] = 'Description is required.';
            }
            if ($formData['location'] === '') {
                $fieldErrors['location'] = 'Location is required.';
            }
            if ($formData['deadline'] === '') {
                $fieldErrors['deadline'] = 'Deadline is required.';
            } else {
                $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $formData['deadline']);
                if (!$deadline) {
                    $fieldErrors['deadline'] = 'Invalid deadline format.';
                } elseif ($deadline <= new \DateTimeImmutable()) {
                    $fieldErrors['deadline'] = 'Deadline must be greater than today.';
                }
            }
            foreach ($formData['skills'] as $index => $skill) {
                if ($skill['name'] === '') {
                    $fieldErrors['skills'][$index]['name'] = 'Skill name is required.';
                }
                if ($skill['level'] === '') {
                    $fieldErrors['skills'][$index]['level'] = 'Skill level is required.';
                } elseif (!in_array($skill['level'], self::SKILL_LEVELS, true)) {
                    $fieldErrors['skills'][$index]['level'] = 'Invalid skill level.';
                }
            }

            if (empty($fieldErrors)) {
                $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $formData['deadline']);
                if ($deadline) {
                    $now = new \DateTimeImmutable();
                    $newId = (string) ((int) round(microtime(true) * 1000) . random_int(100, 999));
                    try {
                        $connection->beginTransaction();

                        $connection->insert('job_offer', [
                            'id' => $newId,
                            'recruiter_id' => $currentRecruiterId,
                            'title' => $formData['title'],
                            'description' => $formData['description'],
                            'location' => $formData['location'],
                            'latitude' => 0,
                            'longitude' => 0,
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
            'contract_type' => (string) ($offer['contract_type'] ?? ''),
            'description' => (string) ($offer['description'] ?? ''),
            'location' => (string) ($offer['location'] ?? ''),
            'deadline' => $deadlineValue,
            'skills' => array_map(static function (array $skill): array {
                return [
                    'name' => (string) ($skill['skill_name'] ?? ''),
                    'level' => (string) ($skill['level_required'] ?? ''),
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

            if ($formData['title'] === '') {
                $fieldErrors['title'] = 'Title is required.';
            }
            if ($formData['contract_type'] === '') {
                $fieldErrors['contract_type'] = 'Contract type is required.';
            } elseif (!in_array($formData['contract_type'], self::CONTRACT_TYPES, true)) {
                $fieldErrors['contract_type'] = 'Please select a valid contract type.';
            }
            if ($formData['description'] === '') {
                $fieldErrors['description'] = 'Description is required.';
            }
            if ($formData['location'] === '') {
                $fieldErrors['location'] = 'Location is required.';
            }
            if ($formData['deadline'] === '') {
                $fieldErrors['deadline'] = 'Deadline is required.';
            } else {
                $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $formData['deadline']);
                if (!$deadline) {
                    $fieldErrors['deadline'] = 'Invalid deadline format.';
                } elseif ($deadline <= new \DateTimeImmutable()) {
                    $fieldErrors['deadline'] = 'Deadline must be greater than today.';
                }
            }

            foreach ($formData['skills'] as $index => $skill) {
                if ($skill['name'] === '') {
                    $fieldErrors['skills'][$index]['name'] = 'Skill name is required.';
                }
                if ($skill['level'] === '') {
                    $fieldErrors['skills'][$index]['level'] = 'Skill level is required.';
                } elseif (!in_array($skill['level'], self::SKILL_LEVELS, true)) {
                    $fieldErrors['skills'][$index]['level'] = 'Invalid skill level.';
                }
            }

            if (empty($fieldErrors)) {
                $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $formData['deadline']);
                if ($deadline) {
                    try {
                        $connection->beginTransaction();

                        $connection->update('job_offer', [
                            'title' => $formData['title'],
                            'description' => $formData['description'],
                            'location' => $formData['location'],
                            'contract_type' => $formData['contract_type'],
                            'deadline' => $deadline->format('Y-m-d H:i:s'),
                            'latitude' => 0,
                            'longitude' => 0,
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

                        // Keep offer edit successful even if warning schema differs in current DB.
                        $hasActiveWarning = 0;
                        try {
                            $hasActiveWarning = (int) $connection->fetchOne(
                                'SELECT COUNT(*) FROM job_offer_warning WHERE job_offer_id = :job_offer_id AND status = :status',
                                ['job_offer_id' => $id, 'status' => 'SENT']
                            );

                            if ($hasActiveWarning > 0) {
                                $connection->update('job_offer_warning', [
                                    'status' => 'RESOLVED',
                                ], [
                                    'job_offer_id' => $id,
                                    'status' => 'SENT',
                                ]);
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
    public function deleteJobOffer(string $id, Request $request, Connection $connection): Response
    {
        $role = $this->resolveSessionRole($request);
        $currentRecruiterId = $this->resolveCurrentRecruiterId($request);
        if ($role !== 'recruiter') {
            $this->addFlash('error', 'Only recruiters can delete job offers.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        try {
            $deletedRows = $connection->delete('job_offer', [
                'id' => $id,
                'recruiter_id' => $currentRecruiterId,
            ]);

            if ($deletedRows > 0) {
                $this->addFlash('success', 'Job offer deleted successfully.');
            } else {
                $this->addFlash('error', 'You can delete only job offers created by you.');
            }
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Unable to delete this job offer.');
        }

        return $this->redirectToRoute('front_job_offers', ['role' => 'recruiter']);
    }

    #[Route('/front/job-applications', name: 'front_job_applications')]
    public function jobApplications(Request $request): Response
    {
        $role = $this->resolveSessionRole($request);
        $cards = [];

        if ($role === 'recruiter') {
            // RECRUITER VIEW: Show applications for their job offers with interview creation capability
            $recruiter = $this->resolveCurrentRecruiter($request);

            if ($recruiter instanceof Recruiter) {
                $ownedOffers = $this->doctrine->getRepository(Job_offer::class)->findBy(['recruiter_id' => $recruiter]);
                if (!empty($ownedOffers)) {
                    $applications = $this->doctrine->getRepository(Job_application::class)->findBy(
                        ['offer_id' => $ownedOffers, 'is_archived' => false],
                        ['applied_at' => 'DESC']
                    );

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

                        $cards[] = [
                            'id' => (string) $application->getId(),
                            'meta' => (string) $application->getCurrent_status(),
                            'title' => 'Offer: ' . ($offer ? $offer->getTitle() : 'Unknown Offer'),
                            'text' => $candidateName . ' | Applied on ' . $application->getApplied_at()->format('d M Y H:i') . ' | Phone: ' . $application->getPhone(),
                            'status' => (string) $application->getCurrent_status(),
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
            }
        } elseif ($role === 'admin') {
            // ADMIN VIEW: read-only access to all applications with full inspection link
            $applications = $this->doctrine->getRepository(Job_application::class)->findBy(
                ['is_archived' => false],
                ['applied_at' => 'DESC']
            );

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
                $applications = $this->doctrine->getRepository(Job_application::class)->findBy(
                    ['candidate_id' => $candidate, 'is_archived' => false],
                    ['applied_at' => 'DESC']
                );

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
        ]);
    }

    #[Route('/front/events', name: 'front_events')]
    public function events(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = $this->resolveSessionRole($request);
        $session = $request->getSession();
        $registeredIds = [];

        $candidate = $this->resolveCurrentCandidate($request);
        $candidateName = trim((string) $session->get('candidate_name', ''));

        if ($candidate instanceof Candidate) {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_id' => $candidate]);
        } elseif ($candidateName !== '') {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_name' => $candidateName]);
        } else {
            $myRegs = [];
        }

        if (count($myRegs) > 0) {
            foreach ($myRegs as $registration) {
                if ($registration->getEvent_id()) {
                    $registeredIds[] = $registration->getEvent_id()->getId();
                }
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
                ], ['id' => 'DESC']);
            }
        } else {
            $events = $entityManager->getRepository(Recruitment_event::class)->findBy([], ['id' => 'DESC']);
        }

        $cards = array_map(static function (Recruitment_event $event) use ($registeredIds): array {
            $description = trim((string) $event->getDescription());

            return [
                'id' => $event->getId(),
                'meta' => sprintf('%s | %s', $event->getEvent_date()->format('d M Y'), (string) $event->getLocation()),
                'title' => (string) $event->getTitle(),
                'text' => $description === '' ? 'No event description available yet.' : substr($description, 0, 190),
                'event_type' => (string) $event->getEvent_type(),
                'location' => (string) $event->getLocation(),
                'capacity' => (int) $event->getCapacity(),
                'meet_link' => (string) $event->getMeet_link(),
                'event_date_value' => $event->getEvent_date()->format('Y-m-d\TH:i'),
                'registered' => in_array($event->getId(), $registeredIds, true),
            ];
        }, $events);

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events/register/{id}', name: 'front_event_register', methods: ['POST'])]
    public function registerEvent(Request $request, int $id, EntityManagerInterface $entityManager): Response
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
        $candidateName = trim((string) $session->get('candidate_name', ''));
        $candidateEmail = trim((string) $session->get('candidate_email', ''));

        if ($candidate instanceof Candidate) {
            $fullName = trim(((string) $candidate->getFirstName()) . ' ' . ((string) $candidate->getLastName()));
            $candidateName = $fullName !== '' ? $fullName : trim((string) $candidate->getFirstName());
            $candidateEmail = (string) $candidate->getEmail();
            $session->set('candidate_name', $candidateName);
            $session->set('candidate_email', $candidateEmail);
        }

        if ($candidateName === '' && $candidateEmail === '') {
            $this->addFlash('warning', 'Please log in with a candidate account to register for events.');
            return $this->redirectToRoute('app_login');
        }

        if (!in_array($id, $registeredIds, true)) {
            $registeredIds[] = $id;
            $session->set('registered_event_ids', $registeredIds);

            $registrationRepository = $entityManager->getRepository(Event_registration::class);
            $queryBuilder = $registrationRepository->createQueryBuilder('er')
                ->where('IDENTITY(er.event_id) = :eventId')
                ->setParameter('eventId', $event->getId());

            if ($candidate instanceof Candidate) {
                $queryBuilder
                    ->andWhere('IDENTITY(er.candidate_id) = :candidateId')
                    ->setParameter('candidateId', $candidate->getId());
            } else {
                $queryBuilder
                    ->andWhere('er.candidate_name = :candidateName')
                    ->setParameter('candidateName', $candidateName);
            }

            $existing = $queryBuilder->getQuery()->getOneOrNullResult();

            if (!$existing) {
                $registration = new Event_registration();
                $registration->setId($this->nextNumericId(Event_registration::class));
                $registration->setEvent_id($event);
                if ($candidate instanceof Candidate) {
                    $registration->setCandidate_id($candidate);
                }
                $registration->setCandidate_name((string) $candidateName);
                $registration->setCandidate_email((string) $candidateEmail);
                $registration->setRegistered_at(new \DateTime());
                $registration->setAttendance_status('registered');

                $entityManager->persist($registration);
                $entityManager->flush();
            }

            $message = sprintf('You have successfully registered for "%s".', $event->getTitle());
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'message' => $message]);
            }
            $this->addFlash('success', $message);
        } else {
            $message = sprintf('You are already registered for "%s".', $event->getTitle());
            if ($request->isXmlHttpRequest()) {
                return $this->json(['warning' => true, 'message' => $message]);
            }
            $this->addFlash('warning', $message);
        }

        return $this->redirectToRoute('front_events', ['role' => 'candidate']);
    }

    #[Route('/front/events/unregister/{id}', name: 'front_event_unregister', methods: ['POST'])]
    public function unregisterEvent(Request $request, int $id, EntityManagerInterface $entityManager): Response
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
        $candidateName = trim((string) $session->get('candidate_name', ''));
        if ($candidate instanceof Candidate || $candidateName !== '') {
            if ($candidate instanceof Candidate) {
                $registration = $entityManager->getRepository(Event_registration::class)->findOneBy([
                    'event_id' => $event,
                    'candidate_id' => $candidate,
                ]);
            } else {
                $registration = $entityManager->getRepository(Event_registration::class)->findOneBy([
                    'event_id' => $event,
                    'candidate_name' => $candidateName,
                ]);
            }

            if ($registration) {
                $entityManager->remove($registration);
                $entityManager->flush();
            }
        }

        $this->addFlash('success', sprintf('You have cancelled registration for "%s".', $event->getTitle()));
        return $this->redirectToRoute('front_event_registrations', ['role' => 'candidate']);
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
        $candidateName = trim((string) $session->get('candidate_name', ''));
        $registeredIds = [];

        if ($candidate instanceof Candidate) {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_id' => $candidate]);
        } elseif ($candidateName !== '') {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_name' => $candidateName]);
        } else {
            $myRegs = [];
        }

        if (count($myRegs) > 0) {
            foreach ($myRegs as $registration) {
                if ($registration->getEvent_id()) {
                    $registeredIds[] = $registration->getEvent_id()->getId();
                }
            }
            $session->set('registered_event_ids', $registeredIds);
        }

        $cards = [];
        if (count($myRegs) > 0) {
            foreach ($myRegs as $registration) {
                $event = $registration->getEvent_id();
                if ($event) {
                    $cards[] = [
                        'id' => $event->getId(),
                        'meta' => $event->getEvent_date()->format('d M Y') . ' | ' . $event->getLocation(),
                        'title' => $event->getTitle(),
                        'text' => $event->getDescription(),
                        'event_type' => $event->getEvent_type(),
                        'location' => $event->getLocation(),
                        'capacity' => $event->getCapacity(),
                        'meet_link' => $event->getMeet_link(),
                        'event_date_value' => $event->getEvent_date()->format('Y-m-d\TH:i'),
                        'status' => $registration->getAttendance_status() ?? 'registered',
                    ];
                }
            }

            usort($cards, static fn (array $a, array $b): int => strcmp($a['event_date_value'], $b['event_date_value']));
        }

        return $this->render('front/modules/event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events/unregister-all', name: 'front_event_unregister_all', methods: ['POST'])]
    public function unregisterAllEvents(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'candidate') {
            $this->addFlash('warning', 'Only candidates can cancel event registrations.');
            return $this->redirectToRoute('front_events');
        }

        $session = $request->getSession();
        $session->set('registered_event_ids', []);

        $candidate = $this->resolveCurrentCandidate($request);
        $candidateName = trim((string) $session->get('candidate_name', ''));
        if ($candidate instanceof Candidate) {
            $registrations = $entityManager->getRepository(Event_registration::class)->findBy([
                'candidate_id' => $candidate,
            ]);
        } elseif ($candidateName !== '') {
            $registrations = $entityManager->getRepository(Event_registration::class)->findBy([
                'candidate_name' => $candidateName,
            ]);
        } else {
            $registrations = [];
        }

        if (count($registrations) > 0) {

            foreach ($registrations as $registration) {
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

        $eventsData = [];
        foreach ($events as $event) {
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
                    $candidateFullName = (string) ($registration->getCandidate_name() ?? 'Unknown');
                }
                if ($candidateEmail === '') {
                    $candidateEmail = (string) ($registration->getCandidate_email() ?? 'N/A');
                }

                $candidatesList[] = [
                    'registration_id' => $registration->getId(),
                    'name' => $candidateFullName,
                    'email' => $candidateEmail,
                    'registered_at' => $registration->getRegistered_at(),
                    'status' => $registration->getAttendance_status() ?? 'registered',
                ];
            }

            $eventsData[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'meta' => $event->getEvent_date()->format('d M Y') . ' | ' . $event->getLocation(),
                'date' => $event->getEvent_date(),
                'location' => $event->getLocation(),
                'capacity' => $event->getCapacity(),
                'event_type' => $event->getEvent_type(),
                'registrations' => $candidatesList,
                'registration_count' => count($candidatesList),
            ];
        }

        return $this->render('front/modules/recruiter_event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'events' => $eventsData,
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

        $events = $entityManager->getRepository(Recruitment_event::class)->findBy([], ['id' => 'DESC']);

        $eventsData = [];
        foreach ($events as $event) {
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
                    $candidateFullName = (string) ($registration->getCandidate_name() ?? 'Unknown');
                }
                if ($candidateEmail === '') {
                    $candidateEmail = (string) ($registration->getCandidate_email() ?? 'N/A');
                }

                $candidatesList[] = [
                    'registration_id' => $registration->getId(),
                    'name' => $candidateFullName,
                    'email' => $candidateEmail,
                    'registered_at' => $registration->getRegistered_at(),
                    'status' => $registration->getAttendance_status() ?? 'registered',
                ];
            }

            $eventsData[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'meta' => $event->getEvent_date()->format('d M Y') . ' | ' . $event->getLocation(),
                'date' => $event->getEvent_date(),
                'location' => $event->getLocation(),
                'capacity' => $event->getCapacity(),
                'event_type' => $event->getEvent_type(),
                'registrations' => $candidatesList,
                'registration_count' => count($candidatesList),
            ];
        }

        return $this->render('front/modules/recruiter_event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'events' => $eventsData,
        ]);
    }

    #[Route('/front/recruiter/event-registrations/{id}/status', name: 'recruiter_update_registration_status', methods: ['POST'])]
    public function updateRegistrationStatus(Request $request, string $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
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
        if (!$event || !$recruiter instanceof Recruiter || $event->getRecruiter_id()->getId() !== $recruiter->getId()) {
            $this->addFlash('warning', 'You can only update registrations for your own events.');
            return $this->redirectToRoute('recruiter_event_registrations', ['role' => 'recruiter']);
        }

        $status = (string) $request->request->get('status');
        if (in_array($status, ['confirmed', 'rejected'], true)) {
            $registration->setAttendance_status($status);
            $entityManager->flush();

            try {
                $this->sendEventRegistrationStatusEmail($registration, $mailer);
            } catch (\Throwable) {
                // Keep status update successful even if mail transport fails.
                $this->addFlash('warning', 'Status was updated, but the notification email could not be sent.');
            }

            $this->addFlash('success', 'Registration status updated to ' . ucfirst($status) . '.');
        } else {
            $this->addFlash('warning', 'Invalid status provided.');
        }

        return $this->redirectToRoute('recruiter_event_registrations', ['role' => 'recruiter']);
    }

    private function sendEventRegistrationStatusEmail(Event_registration $registration, MailerInterface $mailer): void
    {
        $candidateEntity = $registration->getCandidate_id();
        $candidateName = trim((string) ($registration->getCandidate_name() ?? 'Candidate'));
        $candidateEmail = trim((string) ($registration->getCandidate_email() ?? ''));

        if ($candidateEntity instanceof Candidate) {
            $resolvedName = trim(((string) $candidateEntity->getFirstName()) . ' ' . ((string) $candidateEntity->getLastName()));
            if ($resolvedName !== '') {
                $candidateName = $resolvedName;
            }

            $resolvedEmail = trim((string) $candidateEntity->getEmail());
            if ($resolvedEmail !== '') {
                $candidateEmail = $resolvedEmail;
            }
        }

        if ($candidateEmail === '' || !filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $event = $registration->getEvent_id();
        $eventTitle = $event instanceof Recruitment_event ? (string) $event->getTitle() : 'Event';
        $eventType = $event instanceof Recruitment_event ? (string) $event->getEvent_type() : 'General';
        $meetingLink = $event instanceof Recruitment_event ? trim((string) $event->getMeet_link()) : '';
        $eventDate = $event instanceof Recruitment_event && $event->getEvent_date() instanceof \DateTimeInterface
            ? $event->getEvent_date()->format('d M Y H:i')
            : 'To be announced';

        $normalizedStatus = strtolower((string) $registration->getAttendance_status());
        $statusLabel = $normalizedStatus === 'confirmed' ? 'Confirmed' : 'Declined';
        $isConfirmed = $normalizedStatus === 'confirmed';
        $normalizedEventType = strtolower(trim($eventType));
        $isWorkshopOrHiringDay = in_array($normalizedEventType, ['workshop', 'hiring day', 'hiringday'], true);
        $isWebinar = in_array($normalizedEventType, ['webinar', 'webinair'], true);
        $registrationId = (string) $registration->getId();
        $qrPayload = sprintf(
            'TB|REG:%s|EVENT:%s|TYPE:%s|DATE:%s|EMAIL:%s|STATUS:%s',
            $registrationId,
            $eventTitle,
            $eventType,
            $eventDate,
            $candidateEmail,
            strtoupper($statusLabel)
        );
        $qrCodeUrl = $isConfirmed && $isWorkshopOrHiringDay
            ? 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&format=png&data=' . rawurlencode($qrPayload)
            : null;
        $webinarMeetingLink = $isConfirmed && $isWebinar && filter_var($meetingLink, FILTER_VALIDATE_URL)
            ? $meetingLink
            : null;
        $subject = sprintf('Event registration %s - %s', strtolower($statusLabel), $eventTitle);
        $fromAddress = (string) ($_ENV['MAILER_FROM'] ?? 'rayanbenamor207@gmail.com');

        $email = (new Email())
            ->from($fromAddress)
            ->to($candidateEmail)
            ->subject($subject)
            ->html($this->renderView('front/emails/event_registration_status.html.twig', [
                'candidate_name' => $candidateName,
                'status_label' => $statusLabel,
                'is_confirmed' => $isConfirmed,
                'event_title' => $eventTitle,
                'event_type' => $eventType,
                'event_date' => $eventDate,
                'qr_code_url' => $qrCodeUrl,
                'meeting_link' => $webinarMeetingLink,
            ]));

        $mailer->send($email);
    }

    #[Route('/front/interviews', name: 'front_interviews')]
    public function interviews(Request $request): Response
    {
        $role = $this->resolveSessionRole($request);
        $interviews = $this->doctrine->getRepository(Interview::class)->findBy([], ['id' => 'DESC']);

        $cards = [];
        $upcomingInterviews = [];
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

                $upcomingInterviews[] = [
                    'interview_id' => (string) $interview->getId(),
                    'timestamp' => $scheduledAt->getTimestamp(),
                    'date' => $scheduledAt->format('d M Y H:i'),
                    'ymd' => $scheduledAt->format('Y-m-d'),
                    'title' => $title === '' ? 'Untitled offer' : $title,
                    'mode' => strtoupper($mode),
                    'status' => $displayStatus,
                    'location' => $location === '' ? 'N/A' : $location,
                ];
            } catch (Throwable) {
                // Skip malformed rows so one broken interview does not break the page.
                continue;
            }
        }

        usort($upcomingInterviews, static fn (array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
            'upcomingInterviews' => $upcomingInterviews,
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

        $allowedStatuses = ['accepted', 'declined', 'under_review', 'interview_scheduled'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->addFlash('warning', 'Invalid status selected.');
            return $this->redirectToRoute('front_job_applications', $request->query->all());
        }

        $application = $this->doctrine->getRepository(Job_application::class)->find($applicationId);
        if (!$application instanceof Job_application) {
            $this->addFlash('warning', 'Application not found.');
            return $this->redirectToRoute('front_job_applications', $request->query->all());
        }

        $application->setCurrent_status($status);
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

    #[Route('/front/interviews/create/{applicationId}', name: 'front_interview_create', methods: ['GET', 'POST'])]
    public function createInterview(string $applicationId, Request $request): Response
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

            $validation = $this->validateInterviewPayload($formData);
            if ($validation['ok']) {
                $offer = $application->getOffer_id();
                $recruiter = $offer->getRecruiter_id();

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
                $interview->setStatus('scheduled');
                $interview->setCreated_at(new \DateTime());
                $interview->setReminder_sent(false);

                try {
                    $entityManager = $this->doctrine->getManager();
                    $entityManager->persist($interview);
                    $application->setCurrent_status('interview_scheduled');
                    $entityManager->flush();

                    $this->addFlash('success', 'Interview created successfully.');
                    return $this->redirectToRoute('front_interviews', $request->query->all());
                } catch (Throwable) {
                    $this->addFlash('warning', 'Could not create interview. Please check if one already exists for this application.');
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

            $validation = $this->validateInterviewPayload($formData);
            if ($validation['ok']) {
                $interview->setScheduled_at($validation['scheduledAt']);
                $interview->setDuration_minutes($validation['duration']);
                $interview->setMode($validation['mode']);
                $interview->setMeeting_link($validation['meetingLink']);
                $interview->setLocation($validation['location']);
                $interview->setNotes($validation['notes']);
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

        if (!$this->hasActiveInterviewForApplication($application) && (string) $application->getCurrent_status() === 'interview_scheduled') {
            $application->setCurrent_status('under_review');
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

        $interview->setStatus('completed');
        $application = $interview->getApplication_id();
        $application->setCurrent_status($decision === 'accepted' ? 'accepted' : 'declined');

        $entityManager->flush();
        $this->addFlash('success', 'Interview review saved.');

        return $this->redirectToRoute('front_interviews', $request->query->all());
    }

    #[Route('/front/profile', name: 'front_profile')]
    public function profile(Request $request, UsersRepository $userRepo, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $role = $this->resolveSessionRole($request);
        $userId = $request->getSession()->get('user_id');
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
            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $entityManager->flush();
            $request->getSession()->set('user_name', $user->getFirstName());

            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('front_profile');
        }

        return $this->render('front/profile.html.twig', [
            'form' => $form->createView(),
            'authUser' => ['role' => $role],
            'candidateSkills' => $candidateSkills,
        ]);
    }

    private function resolveCurrentUserId(Request $request): string
    {
        return (string) $request->getSession()->get('user_id', '');
    }

    private function resolveSessionRole(Request $request): string
    {
        $roles = (array) $request->getSession()->get('user_roles', []);
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
            $legacyRecruiterId = $this->doctrine->getManager()->getConnection()->fetchOne(
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

    private function validateInterviewPayload(array $data): array
    {
        try {
            $scheduledAt = new \DateTime((string) ($data['scheduled_at'] ?? ''));
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'Invalid interview date/time.'];
        }

        $now = new \DateTimeImmutable();
        if ($scheduledAt <= $now) {
            return ['ok' => false, 'error' => 'Interview date/time must be in the future.'];
        }

        if ($scheduledAt > $now->modify('+' . self::MAX_FUTURE_DAYS . ' days')) {
            return ['ok' => false, 'error' => 'Interview cannot be scheduled more than ' . self::MAX_FUTURE_DAYS . ' days ahead.'];
        }

        $duration = (int) ($data['duration_minutes'] ?? 0);
        if ($duration < 15 || $duration > 240) {
            return ['ok' => false, 'error' => 'Duration must be between 15 and 240 minutes.'];
        }

        $mode = strtolower(trim((string) ($data['mode'] ?? 'online')));
        if (!in_array($mode, ['online', 'onsite'], true)) {
            return ['ok' => false, 'error' => 'Interview mode must be online or onsite.'];
        }

        $meetingLink = trim((string) ($data['meeting_link'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($mode === 'online' && $meetingLink === '') {
            return ['ok' => false, 'error' => 'Meeting link is required for online interviews.'];
        }

        if ($mode === 'online' && !$this->isValidMeetingLink($meetingLink)) {
            return ['ok' => false, 'error' => 'Meeting link must be a valid http(s) URL.'];
        }

        if ($mode === 'onsite' && $location === '') {
            return ['ok' => false, 'error' => 'Location is required for onsite interviews.'];
        }

        if ($mode === 'onsite' && !$this->isValidLocation($location)) {
            return ['ok' => false, 'error' => 'Location can contain letters, numbers and common punctuation (3-120 chars).'];
        }

        if (!$this->isValidTextarea($notes)) {
            return ['ok' => false, 'error' => 'Notes contain unsupported characters or exceed 1000 characters.'];
        }

        return [
            'ok' => true,
            'scheduledAt' => $scheduledAt,
            'duration' => $duration,
            'mode' => $mode,
            'meetingLink' => $meetingLink,
            'location' => $location,
            'notes' => $notes,
        ];
    }

    private function isValidMeetingLink(string $meetingLink): bool
    {
        if (!filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            return false;
        }

        return (bool) preg_match('/^https?:\/\/[\S]+$/i', $meetingLink);
    }

    private function isValidLocation(string $location): bool
    {
        return (bool) preg_match(self::LOCATION_REGEX, $location);
    }

    private function isValidTextarea(string $value): bool
    {
        if (mb_strlen($value) > 1000) {
            return false;
        }

        return (bool) preg_match(self::TEXTAREA_REGEX, $value);
    }

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
            $lockTime = (clone $interview->getScheduled_at())->modify('-' . self::EDIT_LOCK_HOURS . ' hours');
            return new \DateTime() < $lockTime;
        } catch (Throwable) {
            return false;
        }
    }

    private function canSubmitFeedback(Interview $interview): bool
    {
        try {
            $endTime = (clone $interview->getScheduled_at())->modify('+' . $interview->getDuration_minutes() . ' minutes');
            return new \DateTime() >= $endTime;
        } catch (Throwable) {
            return false;
        }
    }

    private function computeCandidateInterviewStatus(Interview $interview, ?Interview_feedback $latestFeedback = null): array
    {
        try {
            $now = new \DateTime();
            $start = $interview->getScheduled_at();
            $end = (clone $start)->modify('+' . $interview->getDuration_minutes() . ' minutes');
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

            $endTime = (clone $interview->getScheduled_at())->modify('+' . $interview->getDuration_minutes() . ' minutes');
            if (new \DateTime() >= $endTime) {
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
        $count = (int) $this->doctrine
            ->getRepository(Interview::class)
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.application_id = :application')
            ->setParameter('application', $application)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function nextNumericId(string $entityClass): string
    {
        $last = $this->doctrine->getRepository($entityClass)->findBy([], ['id' => 'DESC'], 1);
        if (empty($last)) {
            return '1';
        }

        $lastId = (int) $last[0]->getId();
        return (string) ($lastId + 1);
    }

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


