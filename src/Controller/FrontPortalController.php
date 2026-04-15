<?php

namespace App\Controller;

use App\Entity\Interview;
use App\Entity\Interview_feedback;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruitment_event;
use App\Service\Interview\JitsiMeetingLinkGenerator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class FrontPortalController extends AbstractController
{
    private const MAX_FUTURE_DAYS = 90;
    private const EDIT_LOCK_HOURS = 2;
    private const LOCATION_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,120}$/u';
    private const TEXTAREA_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{0,1000}$/u';
    private const REVIEW_COMMENT_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,1000}$/u';

    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $offers = $this->doctrine->getRepository(Job_offer::class)->findBy([], ['id' => 'DESC']);

        $cards = array_map(static function (Job_offer $offer): array {
            $description = trim((string) $offer->getDescription());

            return [
                'meta' => sprintf('%s | %s', (string) $offer->getLocation(), (string) $offer->getContract_type()),
                'title' => (string) $offer->getTitle(),
                'text' => $description === '' ? 'No description available yet.' : substr($description, 0, 190),
            ];
        }, $offers);

        return $this->render('front/modules/job_offers.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/job-applications', name: 'front_job_applications')]
    public function jobApplications(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $applications = $this->doctrine->getRepository(Job_application::class)->findBy([], ['id' => 'DESC']);

        $cards = array_map(function (Job_application $application) use ($request, $role): array {
            $offer = $application->getOffer_id();
            $coverLetter = trim((string) $application->getCover_letter());
            $meta = sprintf('Application #%s | %s', (string) $application->getId(), (string) $application->getCurrent_status());
            $hasActiveInterview = $this->hasActiveInterviewForApplication($application);
            $createInterviewUrl = $this->generateUrl('front_interview_create', ['applicationId' => (string) $application->getId(), 'role' => $role] + $request->query->all());
            $acceptUrl = $this->generateUrl('front_application_set_status', ['applicationId' => (string) $application->getId(), 'status' => 'accepted', 'role' => $role] + $request->query->all());
            $declineUrl = $this->generateUrl('front_application_set_status', ['applicationId' => (string) $application->getId(), 'status' => 'declined', 'role' => $role] + $request->query->all());

            return [
                'id' => (string) $application->getId(),
                'meta' => $meta,
                'title' => sprintf('Offer: %s', (string) $offer->getTitle()),
                'text' => $coverLetter === '' ? 'No cover letter provided.' : substr($coverLetter, 0, 190),
                'status' => (string) $application->getCurrent_status(),
                'create_interview_url' => $hasActiveInterview ? '#' : $createInterviewUrl,
                'can_create_interview' => !$hasActiveInterview,
                'interview_block_reason' => $hasActiveInterview ? 'Interview already created for this application.' : '',
                'accept_url' => $acceptUrl,
                'decline_url' => $declineUrl,
            ];
        }, $applications);

        return $this->render('front/modules/job_applications.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events', name: 'front_events')]
    public function events(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $events = $this->doctrine->getRepository(Recruitment_event::class)->findBy([], ['id' => 'DESC']);

        $cards = array_map(static function (Recruitment_event $event): array {
            $description = trim((string) $event->getDescription());

            return [
                'meta' => sprintf('%s | %s', $event->getEvent_date()->format('d M Y'), (string) $event->getLocation()),
                'title' => (string) $event->getTitle(),
                'text' => $description === '' ? 'No event description available yet.' : substr($description, 0, 190),
            ];
        }, $events);

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/interviews', name: 'front_interviews')]
    public function interviews(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
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
                $mode = $this->normalizeInterviewMode((string) $interview->getMode());
                $duration = (string) $interview->getDuration_minutes();
                $location = trim((string) $interview->getLocation());
                $meetingLink = trim((string) $interview->getMeeting_link());
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

                $canModify = $this->canModifyInterview($interview);
                $editUrl = $this->generateUrl('front_interview_edit', ['id' => (string) $interview->getId(), 'role' => $role] + $request->query->all());
                $deleteUrl = $this->generateUrl('front_interview_delete', ['id' => (string) $interview->getId(), 'role' => $role] + $request->query->all());
                $feedbackUrl = $this->generateUrl('front_interview_feedback', ['id' => (string) $interview->getId(), 'role' => $role] + $request->query->all());

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
                    'can_feedback' => $this->canSubmitFeedback($interview),
                    'review_label' => $hasFeedback ? 'Update Review' : 'Create Review',
                    'edit_url' => $editUrl,
                    'delete_url' => $deleteUrl,
                    'feedback_url' => $feedbackUrl,
                ];

                $upcomingInterviews[] = [
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
        $upcomingInterviews = array_slice($upcomingInterviews, 0, 8);

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
            'upcomingInterviews' => $upcomingInterviews,
        ]);
    }

    #[Route('/front/job-applications/{applicationId}/status/{status}', name: 'front_application_set_status', methods: ['POST'])]
    public function setApplicationStatus(string $applicationId, string $status, Request $request): RedirectResponse
    {
        $role = (string) $request->query->get('role', 'candidate');
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
        $role = (string) $request->query->get('role', 'candidate');
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
    public function createInterview(string $applicationId, Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
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
        $role = (string) $request->query->get('role', 'candidate');
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
        $role = (string) $request->query->get('role', 'candidate');
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
        $role = (string) $request->query->get('role', 'candidate');
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

    private function normalizeInterviewMode(?string $mode): string
    {
        $value = strtolower(trim((string) $mode));
        if ($value === 'onsite' || $value === 'on_site') {
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
}
