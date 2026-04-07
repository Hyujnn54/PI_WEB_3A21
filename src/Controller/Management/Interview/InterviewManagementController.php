<?php

namespace App\Controller\Management\Interview;

use App\Entity\Interview;
use App\Entity\Interview_feedback;
use App\Entity\Job_offer;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InterviewManagementController extends AbstractController
{
    #[Route('/admin/interviews', name: 'management_interviews')]
    public function index(EntityManagerInterface $em): Response
    {
        $interviews = $em->getRepository(Interview::class)->findBy([], ['scheduled_at' => 'DESC']);
        $feedbackRows = $em->getRepository(Interview_feedback::class)->findBy([], ['created_at' => 'DESC']);

        $latestFeedbackByInterviewId = [];
        foreach ($feedbackRows as $feedback) {
            $feedbackInterview = $feedback->getInterview_id();
            if (!$feedbackInterview instanceof Interview) {
                continue;
            }

            $feedbackInterviewId = (string) $feedbackInterview->getId();
            if (!isset($latestFeedbackByInterviewId[$feedbackInterviewId])) {
                $latestFeedbackByInterviewId[$feedbackInterviewId] = $feedback;
            }
        }

        $rows = [];
        $stats = [
            'total' => 0,
            'upcoming' => 0,
            'completed' => 0,
            'pending_feedback' => 0,
            'online' => 0,
            'onsite' => 0,
        ];

        $now = new \DateTimeImmutable();

        foreach ($interviews as $interview) {
            try {
                $stats['total']++;

                $application = $interview->getApplication_id();
                $offer = $application ? $application->getOffer_id() : null;
                $candidate = $application ? $application->getCandidate_id() : null;
                $recruiter = $interview->getRecruiter_id();

                $scheduledAt = $interview->getScheduled_at();
                $duration = max(0, (int) $interview->getDuration_minutes());
                $endAt = (clone $scheduledAt)->modify('+' . $duration . ' minutes');

                if ($scheduledAt > $now) {
                    $stats['upcoming']++;
                }

                $modeKey = strtolower(trim((string) $interview->getMode()));
                $modeKey = $modeKey === 'onsite' ? 'onsite' : 'online';
                if ($modeKey === 'online') {
                    $stats['online']++;
                } else {
                    $stats['onsite']++;
                }

                $rawStatus = strtoupper(trim((string) $interview->getStatus()));
                if ($rawStatus === '') {
                    $rawStatus = 'SCHEDULED';
                }

                $interviewId = (string) $interview->getId();
                $latestFeedback = $latestFeedbackByInterviewId[$interviewId] ?? null;
                $feedbackDecision = $latestFeedback instanceof Interview_feedback
                    ? strtolower(trim((string) $latestFeedback->getDecision()))
                    : '';

                $statusLabel = 'Scheduled';
                $statusClass = 'bg-primary';
                if ($feedbackDecision === 'accepted') {
                    $statusLabel = 'Accepted';
                    $statusClass = 'bg-success';
                } elseif ($feedbackDecision === 'rejected') {
                    $statusLabel = 'Rejected';
                    $statusClass = 'bg-danger';
                } elseif ($rawStatus === 'CANCELLED') {
                    $statusLabel = 'Cancelled';
                    $statusClass = 'bg-danger';
                } elseif ($rawStatus === 'COMPLETED') {
                    $statusLabel = 'Completed';
                    $statusClass = 'bg-success';
                } elseif ($endAt <= $now) {
                    $statusLabel = 'Pending Review';
                    $statusClass = 'bg-warning text-dark';
                    $stats['pending_feedback']++;
                }

                if ($rawStatus === 'COMPLETED' || $latestFeedback instanceof Interview_feedback) {
                    $stats['completed']++;
                }

                $offerTitle = $offer ? trim((string) $offer->getTitle()) : '';
                $candidateName = $candidate ? $this->buildUserLabel($candidate->getId(), 'Unknown candidate') : 'Unknown candidate';
                $recruiterFallback = $recruiter ? trim((string) $recruiter->getCompany_name()) : '';
                $recruiterName = $recruiter
                    ? $this->buildUserLabel($recruiter->getId(), $recruiterFallback !== '' ? $recruiterFallback : 'Unknown recruiter')
                    : 'Unknown recruiter';
                $notes = trim((string) $interview->getNotes());

                $rows[] = [
                    'id' => $interviewId,
                    'offer_title' => $offerTitle !== '' ? $offerTitle : 'Untitled offer',
                    'candidate_name' => $candidateName,
                    'recruiter_name' => $recruiterName,
                    'scheduled_at' => $scheduledAt,
                    'scheduled_ts' => (string) $scheduledAt->getTimestamp(),
                    'duration_minutes' => $duration,
                    'mode_key' => $modeKey,
                    'mode_label' => $modeKey === 'onsite' ? 'Onsite' : 'Online',
                    'status_key' => strtolower(str_replace(' ', '_', $statusLabel)),
                    'status_label' => $statusLabel,
                    'status_class' => $statusClass,
                    'feedback_score' => $latestFeedback instanceof Interview_feedback ? (int) $latestFeedback->getOverall_score() : null,
                    'feedback_decision' => $feedbackDecision !== '' ? ucfirst($feedbackDecision) : 'Pending',
                    'notes_excerpt' => $notes === '' ? 'No interview notes provided.' : mb_strimwidth($notes, 0, 120, '...'),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->render('admin/interviews.html.twig', [
            'authUser' => ['role' => 'admin'],
            'stats' => $stats,
            'rows' => $rows,
        ]);
    }

    #[Route('/admin/interviews/statistics', name: 'management_interviews_statistics')]
    public function statistics(EntityManagerInterface $em): Response
    {
        $interviews = $em->getRepository(Interview::class)->findAll();
        $feedbacks = $em->getRepository(Interview_feedback::class)->findAll();
        $offers = $em->getRepository(Job_offer::class)->findAll();

        $total = count($interviews);
        $scheduled = 0;
        $completed = 0;
        $cancelled = 0;
        $pending = 0;
        $online = 0;
        $onsite = 0;

        $now = new \DateTime();
        $upcomingCount = 0;
        $pastCount = 0;

        $reviewedInterviewIds = [];

        $totalFeedbacks = count($feedbacks);
        $acceptedDecisions = 0;
        $rejectedDecisions = 0;
        $totalScore = 0;

        foreach ($feedbacks as $feedback) {
            $feedbackInterview = $feedback->getInterview_id();
            if ($feedbackInterview instanceof Interview) {
                $reviewedInterviewIds[(string) $feedbackInterview->getId()] = true;
            }

            $decision = strtolower($feedback->getDecision() ?? '');
            if ($decision === 'accepted') {
                $acceptedDecisions++;
            } elseif ($decision === 'rejected') {
                $rejectedDecisions++;
            }
            $totalScore += (int) $feedback->getOverall_score();
        }

        foreach ($interviews as $interview) {
            $status = strtoupper($interview->getStatus());
            $interviewId = (string) $interview->getId();
            $hasReview = isset($reviewedInterviewIds[$interviewId]);

            if ($hasReview || $status === 'COMPLETED') {
                $completed++;
            } else {
                switch ($status) {
                    case 'SCHEDULED':
                        $scheduled++;
                        break;
                    case 'CANCELLED':
                        $cancelled++;
                        break;
                    default:
                        $pending++;
                }
            }

            $mode = strtolower($interview->getMode());
            if ($mode === 'online') {
                $online++;
            } else {
                $onsite++;
            }

            $scheduledAt = $interview->getScheduled_at();
            if ($scheduledAt && $scheduledAt > $now) {
                $upcomingCount++;
            } else {
                $pastCount++;
            }
        }

        $avgScore = $totalFeedbacks > 0 ? round($totalScore / $totalFeedbacks, 1) : 0;
        $acceptanceRate = $totalFeedbacks > 0 ? round(($acceptedDecisions / $totalFeedbacks) * 100, 1) : 0;

        $offerRows = [];
        foreach ($offers as $offer) {
            $offerInterviews = array_filter($interviews, function ($interview) use ($offer) {
                $app = $interview->getApplication_id();
                if (!$app) {
                    return false;
                }
                $offerId = $app->getOffer_id();
                return $offerId && $offerId->getId() === $offer->getId();
            });

            $offerTotal = count($offerInterviews);
            $offerScheduled = 0;
            $offerCompleted = 0;
            $offerCancelled = 0;

            foreach ($offerInterviews as $interview) {
                $status = strtoupper($interview->getStatus());
                $interviewId = (string) $interview->getId();
                $hasReview = isset($reviewedInterviewIds[$interviewId]);

                if ($hasReview || $status === 'COMPLETED') {
                    $offerCompleted++;
                } elseif ($status === 'CANCELLED') {
                    $offerCancelled++;
                } elseif ($status === 'SCHEDULED') {
                    $offerScheduled++;
                }
            }

            $completionRate = $offerTotal > 0 ? round(($offerCompleted / $offerTotal) * 100, 1) : 0;

            $offerRows[] = [
                'offer_title' => $offer->getTitle(),
                'total' => $offerTotal,
                'scheduled' => $offerScheduled,
                'completed' => $offerCompleted,
                'cancelled' => $offerCancelled,
                'completion_rate' => $completionRate,
            ];
        }

        usort($offerRows, fn($a, $b) => $b['total'] - $a['total']);

        return $this->render('admin/interview_statistics.html.twig', [
            'authUser' => ['role' => 'admin'],
            'global' => [
                'total' => $total,
                'scheduled' => $scheduled,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'pending' => $pending,
                'online' => $online,
                'onsite' => $onsite,
                'upcoming' => $upcomingCount,
                'past' => $pastCount,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ],
            'feedback' => [
                'total' => $totalFeedbacks,
                'accepted' => $acceptedDecisions,
                'rejected' => $rejectedDecisions,
                'avg_score' => $avgScore,
                'acceptance_rate' => $acceptanceRate,
            ],
            'offerRows' => $offerRows,
        ]);
    }

    private function buildUserLabel(mixed $user, string $fallback): string
    {
        if (!$user instanceof Users) {
            return $fallback;
        }

        $firstName = trim((string) $user->getFirst_name());
        $lastName = trim((string) $user->getLast_name());
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName !== '') {
            return $fullName;
        }

        $email = trim((string) $user->getEmail());
        if ($email !== '') {
            return $email;
        }

        return $fallback;
    }
}
