<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontPortalController extends AbstractController
{
    private const STATIC_RECRUITER_ID = '1';
    private const CONTRACT_TYPES = ['CDI', 'CDD', 'Internship', 'Freelance', 'Part-time', 'Remote Contract'];
    private const SKILL_LEVELS = ['beginner', 'intermediate', 'advanced'];
    private const JOB_STATUSES = ['open', 'paused', 'closed'];

    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(Request $request, Connection $connection): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $warnings = [];
        $warningStatuses = [];
        $expiredOffers = [];
        $now = new \DateTimeImmutable();

        $cards = [
            ['id' => 1, 'meta' => 'Tunis | CDI | Open', 'title' => 'Frontend Engineer', 'text' => 'Build and iterate candidate-facing experiences with reusable UI modules.', 'can_delete' => false, 'recruiter_id' => '999', 'detail_extra' => ['Type: CDI', 'Location: Tunis', 'Status: Open', 'Deadline: 2026-05-20'], 'warning_status' => null, 'location' => 'Tunis', 'contract_type' => 'CDI', 'status' => 'open', 'deadline' => '2026-05-20'],
            ['id' => 2, 'meta' => 'Sfax | CDI | Open', 'title' => 'Symfony Backend Developer', 'text' => 'Maintain recruitment workflows and implement stable API endpoints.', 'can_delete' => false, 'recruiter_id' => '999', 'detail_extra' => ['Type: CDI', 'Location: Sfax', 'Status: Open', 'Deadline: 2026-05-22'], 'warning_status' => null, 'location' => 'Sfax', 'contract_type' => 'CDI', 'status' => 'open', 'deadline' => '2026-05-22'],
            ['id' => 3, 'meta' => 'Remote | Contract | Paused', 'title' => 'Recruitment Data Analyst', 'text' => 'Track funnel metrics and transform hiring data into useful insights.', 'can_delete' => false, 'recruiter_id' => '999', 'detail_extra' => ['Type: Contract', 'Location: Remote', 'Status: Paused', 'Deadline: 2026-05-25'], 'warning_status' => null, 'location' => 'Remote', 'contract_type' => 'Contract', 'status' => 'paused', 'deadline' => '2026-05-25'],
        ];

        try {
            if ($role === 'recruiter') {
                $connection->executeStatement(
                    'UPDATE job_offer SET status = :closed_status WHERE deadline IS NOT NULL AND deadline < :now AND status <> :closed_status',
                    [
                        'closed_status' => 'closed',
                        'now' => $now->format('Y-m-d H:i:s'),
                    ]
                );
            }

            $rows = $connection->fetchAllAssociative(
                'SELECT id, recruiter_id, title, description, location, contract_type, status, deadline FROM job_offer ORDER BY created_at DESC LIMIT 25'
            );

            $dbCards = array_map(function (array $row) use ($connection, $now): array {
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
                    'can_delete' => (string) $row['recruiter_id'] === self::STATIC_RECRUITER_ID,
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

                // Candidates should not see expired offers.
                if ($role === 'candidate' && ($dbCard['is_expired'] ?? false) === true) {
                    continue;
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
                    ['recruiter_id' => self::STATIC_RECRUITER_ID, 'status' => 'SENT']
                );
            } catch (\Throwable $exception) {
                $warnings = [];
            }

            // Fetch warning statuses for all recruiter's jobs (for blue highlighting when pending review)
            try {
                $resolvedWarnings = $connection->fetchAllAssociative(
                    'SELECT job_offer_id FROM job_offer_warning WHERE recruiter_id = :recruiter_id AND status = :status',
                    ['recruiter_id' => self::STATIC_RECRUITER_ID, 'status' => 'RESOLVED']
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
            'warnings' => $warnings,
            'expiredOffers' => $expiredOffers,
        ]);
    }

    #[Route('/front/job-offers/new', name: 'front_job_offer_new', methods: ['GET', 'POST'])]
    public function createJobOffer(Request $request, Connection $connection): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        if ($role !== 'recruiter') {
            $this->addFlash('error', 'Only recruiters can create job offers.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $location = trim((string) $request->request->get('location', ''));
            $contractType = trim((string) $request->request->get('contract_type', ''));
            $jobStatus = trim((string) $request->request->get('status', 'open'));
            $deadlineInput = trim((string) $request->request->get('deadline', ''));
            $skills = $this->normalizeSkills((array) $request->request->all('skills'));

            if ($title === '' || $description === '' || $location === '' || $contractType === '' || $jobStatus === '' || $deadlineInput === '' || count($skills) === 0) {
                $this->addFlash('error', 'Please fill in all required fields.');
            } elseif (!in_array($contractType, self::CONTRACT_TYPES, true)) {
                $this->addFlash('error', 'Please select a valid contract type.');
            } elseif (!in_array($jobStatus, self::JOB_STATUSES, true)) {
                $this->addFlash('error', 'Please select a valid status.');
            } else {
                $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $deadlineInput);
                if (!$deadline) {
                    $this->addFlash('error', 'Invalid deadline format.');
                } else {
                    $now = new \DateTimeImmutable();
                    $newId = (string) ((int) round(microtime(true) * 1000) . random_int(100, 999));
                    try {
                        $connection->beginTransaction();

                        $connection->insert('job_offer', [
                            'id' => $newId,
                            'recruiter_id' => self::STATIC_RECRUITER_ID,
                            'title' => $title,
                            'description' => $description,
                            'location' => $location,
                            'latitude' => (float) $request->request->get('latitude', 0),
                            'longitude' => (float) $request->request->get('longitude', 0),
                            'contract_type' => $contractType,
                            'created_at' => $now->format('Y-m-d H:i:s'),
                            'deadline' => $deadline->format('Y-m-d H:i:s'),
                            'status' => $jobStatus,
                            'quality_score' => 100,
                            'ai_suggestions' => '',
                            'is_flagged' => 0,
                            'flagged_at' => $now->format('Y-m-d H:i:s'),
                        ]);

                        foreach ($skills as $skill) {
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
        ]);
    }

    #[Route('/front/job-offers/{id}/edit', name: 'front_job_offer_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editJobOffer(string $id, Request $request, Connection $connection): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        if ($role !== 'recruiter') {
            $this->addFlash('error', 'Only recruiters can update job offers.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        $offer = $connection->fetchAssociative(
            'SELECT id, recruiter_id, title, description, location, latitude, longitude, contract_type, deadline FROM job_offer WHERE id = :id AND recruiter_id = :recruiter_id LIMIT 1',
            [
                'id' => $id,
                'recruiter_id' => self::STATIC_RECRUITER_ID,
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

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $location = trim((string) $request->request->get('location', ''));
            $contractType = trim((string) $request->request->get('contract_type', ''));
            $deadlineInput = trim((string) $request->request->get('deadline', ''));
            $skills = $this->normalizeSkills((array) $request->request->all('skills'));

            if ($title === '' || $description === '' || $location === '' || $contractType === '' || $deadlineInput === '' || count($skills) === 0) {
                $this->addFlash('error', 'Please fill in all required fields.');
            } elseif (!in_array($contractType, self::CONTRACT_TYPES, true)) {
                $this->addFlash('error', 'Please select a valid contract type.');
            } else {
                $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $deadlineInput);
                if (!$deadline) {
                    $this->addFlash('error', 'Invalid deadline format.');
                } else {
                    try {
                        $connection->beginTransaction();

                        $connection->update('job_offer', [
                            'title' => $title,
                            'description' => $description,
                            'location' => $location,
                            'contract_type' => $contractType,
                            'deadline' => $deadline->format('Y-m-d H:i:s'),
                            'latitude' => (float) $request->request->get('latitude', 0),
                            'longitude' => (float) $request->request->get('longitude', 0),
                        ], [
                            'id' => $id,
                            'recruiter_id' => self::STATIC_RECRUITER_ID,
                        ]);

                        $connection->delete('offer_skill', ['offer_id' => $id]);
                        foreach ($skills as $skill) {
                            $connection->insert('offer_skill', [
                                'id' => (string) ((int) round(microtime(true) * 1000) . random_int(100, 999)),
                                'offer_id' => $id,
                                'skill_name' => $skill['name'],
                                'level_required' => $skill['level'],
                            ]);
                        }

                        // If job has an active warning, mark it as resolved (recruiter edited)
                        $hasActiveWarning = (int) $connection->fetchOne(
                            'SELECT COUNT(*) FROM job_offer_warning WHERE job_offer_id = :job_offer_id AND status = :status',
                            ['job_offer_id' => $id, 'status' => 'SENT']
                        );

                        if ($hasActiveWarning > 0) {
                            $connection->update('job_offer_warning', [
                                'status' => 'RESOLVED',
                                'edited_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                            ], [
                                'job_offer_id' => $id,
                                'status' => 'SENT',
                            ]);
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

            $offer = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'location' => $location,
                'contract_type' => $contractType,
                'deadline' => $deadlineInput,
                'latitude' => (string) $request->request->get('latitude', '0'),
                'longitude' => (string) $request->request->get('longitude', '0'),
            ];
        }

        return $this->render('front/modules/job_offer_edit.html.twig', [
            'authUser' => ['role' => $role],
            'offer' => $offer,
            'skills' => $skills,
            'contractTypes' => self::CONTRACT_TYPES,
            'skillLevels' => self::SKILL_LEVELS,
        ]);
    }

    #[Route('/front/job-offers/{id}/delete', name: 'front_job_offer_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteJobOffer(string $id, Request $request, Connection $connection): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        if ($role !== 'recruiter') {
            $this->addFlash('error', 'Only recruiters can delete job offers.');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        try {
            $deletedRows = $connection->delete('job_offer', [
                'id' => $id,
                'recruiter_id' => self::STATIC_RECRUITER_ID,
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
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => 'Application #1021 | Under Review', 'title' => 'Offer: Frontend Engineer', 'text' => 'Your profile passed initial screening and is awaiting recruiter feedback.'],
            ['meta' => 'Application #1022 | Interview Scheduled', 'title' => 'Offer: Symfony Backend Developer', 'text' => 'Technical interview is planned and pending confirmation details.'],
            ['meta' => 'Application #1023 | Accepted', 'title' => 'Offer: QA Engineer', 'text' => 'Your application has been approved and onboarding steps are prepared.'],
        ];

        return $this->render('front/modules/job_applications.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events', name: 'front_events')]
    public function events(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => '12 Apr 2026 | Tunis', 'title' => 'Tech Hiring Day', 'text' => 'Meet recruiters and discover active engineering opportunities.'],
            ['meta' => '20 Apr 2026 | Sousse', 'title' => 'Career Talk', 'text' => 'Panel discussion with hiring managers and senior developers.'],
            ['meta' => '28 Apr 2026 | Remote', 'title' => 'Virtual Assessment Workshop', 'text' => 'Online guidance session for interview and coding assessments.'],
        ];

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/interviews', name: 'front_interviews')]
    public function interviews(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => '15 Apr 2026 | 10:00 | Scheduled', 'title' => 'Interview: Frontend Engineer', 'text' => 'Prepare portfolio walkthrough and component design discussion.'],
            ['meta' => '18 Apr 2026 | 14:30 | Pending Feedback', 'title' => 'Interview: Symfony Backend Developer', 'text' => 'Technical round completed, feedback consolidation in progress.'],
            ['meta' => '22 Apr 2026 | 09:30 | Completed', 'title' => 'Interview: QA Engineer', 'text' => 'Process completed, final decision and follow-up underway.'],
        ];

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
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
}
