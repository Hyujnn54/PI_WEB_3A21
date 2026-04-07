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

    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(Request $request, Connection $connection): Response
    {
        $role = (string) $request->query->get('role', 'candidate');

        $cards = [
            ['id' => 1, 'meta' => 'Tunis | CDI', 'title' => 'Frontend Engineer', 'text' => 'Build and iterate candidate-facing experiences with reusable UI modules.', 'can_delete' => false],
            ['id' => 2, 'meta' => 'Sfax | CDI', 'title' => 'Symfony Backend Developer', 'text' => 'Maintain recruitment workflows and implement stable API endpoints.', 'can_delete' => false],
            ['id' => 3, 'meta' => 'Remote | Contract', 'title' => 'Recruitment Data Analyst', 'text' => 'Track funnel metrics and transform hiring data into useful insights.', 'can_delete' => false],
        ];

        try {
            $rows = $connection->fetchAllAssociative(
                'SELECT id, recruiter_id, title, description, location, contract_type FROM job_offer ORDER BY created_at DESC LIMIT 25'
            );

            $dbCards = array_map(function (array $row): array {
                return [
                    'id' => (string) $row['id'],
                    'meta' => sprintf('%s | %s', (string) $row['location'], (string) $row['contract_type']),
                    'title' => (string) $row['title'],
                    'text' => (string) $row['description'],
                    'can_delete' => (string) $row['recruiter_id'] === self::STATIC_RECRUITER_ID,
                ];
            }, $rows);

            $cards = array_merge($dbCards, $cards);
        } catch (\Throwable $exception) {
            // Keep UI usable even if table is not ready in current environment.
        }

        return $this->render('front/modules/job_offers.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
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
            $deadlineInput = trim((string) $request->request->get('deadline', ''));

            if ($title === '' || $description === '' || $location === '' || $contractType === '' || $deadlineInput === '') {
                $this->addFlash('error', 'Please fill in all required fields.');
            } else {
                $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $deadlineInput);
                if (!$deadline) {
                    $this->addFlash('error', 'Invalid deadline format.');
                } else {
                    $now = new \DateTimeImmutable();
                    $newId = (string) ((int) round(microtime(true) * 1000) . random_int(100, 999));

                    try {
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
                            'status' => 'open',
                            'quality_score' => 100,
                            'ai_suggestions' => '',
                            'is_flagged' => 0,
                            'flagged_at' => $now->format('Y-m-d H:i:s'),
                        ]);

                        $this->addFlash('success', 'Job offer created successfully.');
                        return $this->redirectToRoute('front_job_offers', ['role' => 'recruiter']);
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', 'Failed to create job offer. Check DB constraints for recruiter_id.');
                    }
                }
            }
        }

        return $this->render('front/modules/job_offer_new.html.twig', [
            'authUser' => ['role' => $role],
            'contractTypes' => ['CDI', 'CDD', 'Internship', 'Freelance', 'Part-time', 'Remote Contract'],
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
}
