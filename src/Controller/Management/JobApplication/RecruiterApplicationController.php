<?php

namespace App\Controller\Management\JobApplication;

use App\Entity\Application_status_history;
use App\Entity\Job_application;
use App\Entity\Recruiter;
use App\Repository\Application_status_historyRepository;
use App\Repository\Job_applicationRepository;
use App\Service\Translation\GroqLanguageDetector;
use App\Service\Translation\GroqTranslator;
use App\Service\Translation\LibreTranslateClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecruiterApplicationController extends AbstractController
{
    private const APPLICATION_STATUSES = [
        'SUBMITTED',
        'IN_REVIEW',
        'SHORTLISTED',
        'REJECTED',
        'INTERVIEW',
        'HIRED',
    ];

    private const TRANSLATABLE_LANGUAGES = [
        'en' => 'English',
        'fr' => 'French',
        'ar' => 'Arabic',
    ];

    #[Route('/applicationmanagement/recruiter/applications/{applicationId}/details', name: 'app_recruiter_application_details')]
    public function details(
        int $applicationId,
        Request $request,
        EntityManagerInterface $em,
        Application_status_historyRepository $historyRepository
    ): Response
    {
        $recruiterId = (string) $request->getSession()->get('user_id', '');
        $recruiter = $em->getRepository(Recruiter::class)->find($recruiterId);

        if (!$recruiter) {
            $this->addFlash('error', 'Recruiter not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$this->recruiterOwnsApplication($recruiter, $application)) {
            $this->addFlash('error', 'Application not found for your offers.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        $historyEntries = $historyRepository->findForApplication($application);

        $editableHistoryIds = [];
        foreach ($historyEntries as $entry) {
            $editableHistoryIds[(string) $entry->getId()] = $this->isHistoryEditableByRecruiter($entry, $recruiter);
        }

        $statusOptions = [
            ['value' => 'SUBMITTED', 'label' => 'Submitted'],
            ['value' => 'IN_REVIEW', 'label' => 'In Review'],
            ['value' => 'SHORTLISTED', 'label' => 'Shortlisted'],
            ['value' => 'REJECTED', 'label' => 'Rejected'],
            ['value' => 'INTERVIEW', 'label' => 'Interview'],
            ['value' => 'HIRED', 'label' => 'Hired'],
        ];

        return $this->render('management/job_application/recruiter_details.html.twig', [
            'application' => $application,
            'offer' => $application->getOffer_id(),
            'candidate' => $application->getCandidate_id(),
            'historyEntries' => $historyEntries,
            'editableHistoryIds' => $editableHistoryIds,
            'statusOptions' => $statusOptions,
        ]);
    }

    #[Route('/applicationmanagement/recruiter/applications/{applicationId}/status', name: 'app_recruiter_application_update_status', methods: ['POST'])]
    public function updateStatus(int $applicationId, Request $request, EntityManagerInterface $em): Response
    {
        $recruiterId = (string) $request->getSession()->get('user_id', '');
        $recruiter = $em->getRepository(Recruiter::class)->find($recruiterId);

        if (!$recruiter) {
            $this->addFlash('error', 'Recruiter not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$this->recruiterOwnsApplication($recruiter, $application)) {
            $this->addFlash('error', 'Application not found for your offers.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        $newStatus = strtoupper(trim((string) $request->request->get('status', '')));
        if ($newStatus === '') {
            $this->addFlash('warning', 'Please choose a status.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        if (!in_array($newStatus, self::APPLICATION_STATUSES, true)) {
            $this->addFlash('error', 'Invalid status selected.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        $oldStatus = strtoupper(trim((string) $application->getCurrent_status()));
        $application->setCurrent_status($newStatus);

        $note = trim((string) $request->request->get('note', ''));
        $recruiterName = $this->resolveRecruiterDisplayName($recruiter);
        if ($note === '') {
            $note = $this->generateAutoRecruiterNote($recruiterName, $oldStatus, $newStatus);
        }

        $history = new Application_status_history();
        $history->setApplication_id($application);
        $history->setStatus($newStatus);
        $history->setChanged_at(new \DateTime());
        $history->setChanged_by($recruiter);
        $history->setNote($note);

        $em->persist($history);
        $em->flush();

        $this->addFlash('success', 'Application status updated successfully.');

        $returnTo = strtolower(trim((string) $request->request->get('return_to', 'details')));
        if ($returnTo === 'list') {
            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
    }

    #[Route('/applicationmanagement/recruiter/applications/{applicationId}/cover-letter/detect-language', name: 'app_recruiter_cover_letter_detect_language', methods: ['POST'])]
    public function detectCoverLetterLanguage(
        int $applicationId,
        Request $request,
        EntityManagerInterface $em,
        GroqLanguageDetector $groqLanguageDetector
    ): JsonResponse {
        $recruiterId = (string) $request->getSession()->get('user_id', '');
        $recruiter = $em->getRepository(Recruiter::class)->find($recruiterId);

        if (!$recruiter) {
            return $this->json([
                'ok' => false,
                'error' => 'Recruiter not found.',
            ], Response::HTTP_FORBIDDEN);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$this->recruiterOwnsApplication($recruiter, $application)) {
            return $this->json([
                'ok' => false,
                'error' => 'Application not found for your offers.',
            ], Response::HTTP_NOT_FOUND);
        }

        $coverLetter = trim((string) $application->getCover_letter());
        if ($coverLetter === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Cover letter is empty and cannot be detected.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $sourceLanguage = $groqLanguageDetector->detectLanguageCode($coverLetter, array_keys(self::TRANSLATABLE_LANGUAGES));
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $targets = [];
        foreach (self::TRANSLATABLE_LANGUAGES as $code => $label) {
            if ($code === $sourceLanguage) {
                continue;
            }

            $targets[] = [
                'code' => $code,
                'label' => $label,
            ];
        }

        return $this->json([
            'ok' => true,
            'sourceLanguage' => $sourceLanguage,
            'sourceLabel' => self::TRANSLATABLE_LANGUAGES[$sourceLanguage] ?? strtoupper($sourceLanguage),
            'targets' => $targets,
        ]);
    }

    #[Route('/applicationmanagement/recruiter/applications/{applicationId}/cover-letter/translate', name: 'app_recruiter_cover_letter_translate', methods: ['POST'])]
    public function translateCoverLetter(
        int $applicationId,
        Request $request,
        EntityManagerInterface $em,
        LibreTranslateClient $libreTranslateClient,
        GroqTranslator $groqTranslator
    ): JsonResponse {
        $recruiterId = (string) $request->getSession()->get('user_id', '');
        $recruiter = $em->getRepository(Recruiter::class)->find($recruiterId);

        if (!$recruiter) {
            return $this->json([
                'ok' => false,
                'error' => 'Recruiter not found.',
            ], Response::HTTP_FORBIDDEN);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$this->recruiterOwnsApplication($recruiter, $application)) {
            return $this->json([
                'ok' => false,
                'error' => 'Application not found for your offers.',
            ], Response::HTTP_NOT_FOUND);
        }

        $sourceLanguage = strtolower(trim((string) $request->request->get('source_language', '')));
        $targetLanguage = strtolower(trim((string) $request->request->get('target_language', '')));

        if (!isset(self::TRANSLATABLE_LANGUAGES[$sourceLanguage])) {
            return $this->json([
                'ok' => false,
                'error' => 'Unsupported source language.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!isset(self::TRANSLATABLE_LANGUAGES[$targetLanguage])) {
            return $this->json([
                'ok' => false,
                'error' => 'Unsupported target language.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($sourceLanguage === $targetLanguage) {
            return $this->json([
                'ok' => false,
                'error' => 'Source and target languages cannot be the same.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $coverLetter = trim((string) $application->getCover_letter());
        if ($coverLetter === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Cover letter is empty and cannot be translated.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $translatedText = '';
        $translationProvider = '';

        if ($this->shouldUseGroqTranslation($sourceLanguage, $targetLanguage)) {
            try {
                $translatedText = $groqTranslator->translate($coverLetter, $sourceLanguage, $targetLanguage);
                $translationProvider = 'groq';
            } catch (\Throwable $exception) {
                return $this->json([
                    'ok' => false,
                    'error' => $exception->getMessage(),
                ], Response::HTTP_BAD_GATEWAY);
            }
        } else {
            try {
                $translatedText = $libreTranslateClient->translate($coverLetter, $sourceLanguage, $targetLanguage);
                $translationProvider = 'libretranslate';
            } catch (\Throwable $libreException) {
                try {
                    // Fallback to Groq when local LibreTranslate is temporarily unavailable.
                    $translatedText = $groqTranslator->translate($coverLetter, $sourceLanguage, $targetLanguage);
                    $translationProvider = 'groq_fallback';
                } catch (\Throwable $groqException) {
                    return $this->json([
                        'ok' => false,
                        'error' => 'LibreTranslate is unavailable and Groq fallback failed. ' . $groqException->getMessage(),
                    ], Response::HTTP_BAD_GATEWAY);
                }
            }
        }

        return $this->json([
            'ok' => true,
            'translatedText' => $translatedText,
            'targetLanguage' => $targetLanguage,
            'targetLabel' => self::TRANSLATABLE_LANGUAGES[$targetLanguage],
            'translationProvider' => $translationProvider,
        ]);
    }

    #[Route('/applicationmanagement/recruiter/applications/bulk-status', name: 'app_recruiter_application_bulk_update_status', methods: ['POST'])]
    public function bulkUpdateStatus(
        Request $request,
        EntityManagerInterface $em,
        Job_applicationRepository $jobApplicationRepository
    ): Response {
        $recruiterId = (string) $request->getSession()->get('user_id', '');
        $recruiter = $em->getRepository(Recruiter::class)->find($recruiterId);

        if (!$recruiter) {
            $this->addFlash('error', 'Recruiter not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        $newStatus = strtoupper(trim((string) $request->request->get('status', '')));
        if (!in_array($newStatus, self::APPLICATION_STATUSES, true)) {
            $this->addFlash('error', 'Invalid status selected for bulk update.');

            return $this->redirectToRoute('front_job_applications', $this->buildRecruiterListRouteParams($request));
        }

        $applicationIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->request->all('application_ids')),
            static fn (int $id): bool => $id > 0
        )));

        if ($applicationIds === []) {
            $this->addFlash('warning', 'Please select at least one application.');

            return $this->redirectToRoute('front_job_applications', $this->buildRecruiterListRouteParams($request));
        }

        $applications = $jobApplicationRepository->findForRecruiterByIds($recruiter, $applicationIds);
        if ($applications === []) {
            $this->addFlash('warning', 'No eligible applications were found for bulk update.');

            return $this->redirectToRoute('front_job_applications', $this->buildRecruiterListRouteParams($request));
        }

        $manualNote = trim((string) $request->request->get('note', ''));
        $recruiterName = $this->resolveRecruiterDisplayName($recruiter);
        $updatedCount = 0;

        foreach ($applications as $application) {
            $oldStatus = strtoupper(trim((string) $application->getCurrent_status()));
            $application->setCurrent_status($newStatus);

            $history = new Application_status_history();
            $history->setApplication_id($application);
            $history->setStatus($newStatus);
            $history->setChanged_at(new \DateTime());
            $history->setChanged_by($recruiter);
            $history->setNote($manualNote !== '' ? $manualNote : $this->generateAutoRecruiterNote($recruiterName, $oldStatus, $newStatus));

            $em->persist($history);
            $updatedCount++;
        }

        $em->flush();

        $skippedCount = count($applicationIds) - count($applications);
        $message = $updatedCount . ' application(s) updated to ' . $newStatus . '.';
        if ($skippedCount > 0) {
            $message .= ' ' . $skippedCount . ' skipped (not found, archived, or not yours).';
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('front_job_applications', $this->buildRecruiterListRouteParams($request));
    }

    #[Route('/applicationmanagement/recruiter/applications/{applicationId}/history/{historyId}/note', name: 'app_recruiter_history_note_update', methods: ['POST'])]
    public function updateHistoryNote(
        int $applicationId,
        int $historyId,
        Request $request,
        EntityManagerInterface $em,
        Application_status_historyRepository $historyRepository
    ): Response {
        $recruiterId = (string) $request->getSession()->get('user_id', '');
        $recruiter = $em->getRepository(Recruiter::class)->find($recruiterId);

        if (!$recruiter) {
            $this->addFlash('error', 'Recruiter not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$this->recruiterOwnsApplication($recruiter, $application)) {
            $this->addFlash('error', 'Application not found for your offers.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'recruiter']);
        }

        $history = $historyRepository->findForApplicationById($application, $historyId);
        if (!$history) {
            $this->addFlash('error', 'History entry not found.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        if (!$this->isHistoryEditableByRecruiter($history, $recruiter)) {
            $this->addFlash('warning', 'You can edit only your own manually written notes.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        $currentNote = trim((string) $history->getNote());
        $newNote = trim((string) $request->request->get('note', ''));
        if ($newNote === '') {
            $this->addFlash('warning', 'Note cannot be empty.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        if ($newNote === $currentNote) {
            $this->addFlash('info', 'No changes detected in the note.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        $history->setNote($newNote);
        $em->flush();

        $this->addFlash('success', 'Status note updated successfully.');

        return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
    }

    private function recruiterOwnsApplication(Recruiter $recruiter, ?Job_application $application): bool
    {
        if (!$application) {
            return false;
        }

        if ($application->getIs_archived()) {
            return false;
        }

        $offer = $application->getOffer_id();
        if (!$offer) {
            return false;
        }

        $offerRecruiter = $offer->getRecruiter_id();
        if (!$offerRecruiter) {
            return false;
        }

        return (string) $offerRecruiter->getId() === (string) $recruiter->getId();
    }

    private function resolveRecruiterDisplayName(Recruiter $recruiter): string
    {
        $firstName = (string) $recruiter->getFirstName();
        $lastName = (string) $recruiter->getLastName();
        $fullName = trim($firstName . ' ' . $lastName);

        return $fullName !== '' ? $fullName : 'Recruiter';
    }

    private function generateAutoRecruiterNote(string $recruiterName, string $oldStatus, string $newStatus): string
    {
        if ($oldStatus === $newStatus) {
            return $recruiterName . ' reviewed the application and kept the status as ' . $newStatus . '.';
        }

        $statusMessages = [
            'SUBMITTED' => $recruiterName . ' set the application status to Submitted.',
            'IN_REVIEW' => $recruiterName . ' is reviewing the application.',
            'SHORTLISTED' => $recruiterName . ' shortlisted the application for the next step.',
            'REJECTED' => $recruiterName . ' rejected the application.',
            'INTERVIEW' => $recruiterName . ' moved the application to Interview stage.',
            'HIRED' => $recruiterName . ' marked the application as Hired.',
        ];

        return $statusMessages[$newStatus] ?? ($recruiterName . ' changed status from ' . $oldStatus . ' to ' . $newStatus . '.');
    }

    private function isAutoGeneratedRecruiterNote(string $note): bool
    {
        $patterns = [
            '/^.+ reviewed the application and kept the status as [A-Z_]+\.$/',
            '/^.+ set the application status to Submitted\.$/',
            '/^.+ is reviewing the application\.$/',
            '/^.+ shortlisted the application for the next step\.$/',
            '/^.+ rejected the application\.$/',
            '/^.+ moved the application to Interview stage\.$/',
            '/^.+ marked the application as Hired\.$/',
            '/^.+ changed status from [A-Z_]+ to [A-Z_]+\.$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $note) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isHistoryEditableByRecruiter(Application_status_history $history, Recruiter $recruiter): bool
    {
        if ($this->isAutoGeneratedRecruiterNote((string) $history->getNote())) {
            return false;
        }

        $historyAuthor = $history->getChanged_by();
        $recruiterId = $recruiter->getId();

        if (!$historyAuthor || !$recruiterId) {
            return false;
        }

        return (string) $historyAuthor->getId() === (string) $recruiterId;
    }

    private function shouldUseGroqTranslation(string $sourceLanguage, string $targetLanguage): bool
    {
        return $sourceLanguage === 'fr' || $targetLanguage === 'fr';
    }

    /**
     * @return array{role: string, search: string, status: string, sort: string}
     */
    private function buildRecruiterListRouteParams(Request $request): array
    {
        return [
            'role' => 'recruiter',
            'search' => trim((string) $request->request->get('search', '')),
            'status' => strtolower(trim((string) $request->request->get('status_filter', 'all'))),
            'sort' => strtolower(trim((string) $request->request->get('sort', 'date_desc'))),
        ];
    }
}
