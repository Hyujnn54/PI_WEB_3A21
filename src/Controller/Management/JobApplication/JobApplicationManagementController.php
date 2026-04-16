<?php

namespace App\Controller\Management\JobApplication;

use App\Entity\Admin;
use App\Entity\Application_status_history;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Repository\Job_applicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobApplicationManagementController extends AbstractController
{
    private const APPLICATION_STATUSES = [
        'SUBMITTED',
        'IN_REVIEW',
        'SHORTLISTED',
        'REJECTED',
        'INTERVIEW',
        'HIRED',
    ];

    #[Route('/applicationmanagement/admin/applications-statistics', name: 'management_job_applications_statistics')]
    public function statistics(EntityManagerInterface $em): Response
    {
        $offers = $em->getRepository(Job_offer::class)->findBy([], ['created_at' => 'DESC']);
        $applications = $em->getRepository(Job_application::class)->findBy([]);

        $offerRows = [];
        foreach ($offers as $offer) {
            $offerId = (string) $offer->getId();
            $offerRows[$offerId] = [
                'offer_id' => $offerId,
                'offer_title' => (string) $offer->getTitle(),
                'total' => 0,
                'submitted' => 0,
                'shortlisted' => 0,
                'rejected' => 0,
                'interview' => 0,
                'hired' => 0,
                'acceptance_rate' => 0,
            ];
        }

        $global = [
            'total' => 0,
            'submitted' => 0,
            'shortlisted' => 0,
            'rejected' => 0,
            'interview' => 0,
            'hired' => 0,
            'acceptance_rate' => 0,
        ];

        foreach ($applications as $application) {
            $status = strtoupper(trim((string) $application->getCurrent_status()));
            $normalizedStatus = match ($status) {
                'SHORTLISTED' => 'SHORTLISTED',
                'REJECTED', 'DECLINED' => 'REJECTED',
                'INTERVIEW', 'INTERVIEW_SCHEDULED' => 'INTERVIEW',
                'HIRED', 'ACCEPTED' => 'HIRED',
                default => 'SUBMITTED',
            };
            $offer = $application->getOffer_id();
            $offerId = $offer ? (string) $offer->getId() : null;

            $global['total']++;
            if ($normalizedStatus === 'SUBMITTED') {
                $global['submitted']++;
            } elseif ($normalizedStatus === 'SHORTLISTED') {
                $global['shortlisted']++;
            } elseif ($normalizedStatus === 'REJECTED') {
                $global['rejected']++;
            } elseif ($normalizedStatus === 'INTERVIEW') {
                $global['interview']++;
            } elseif ($normalizedStatus === 'HIRED') {
                $global['hired']++;
            }

            if ($offerId === null || !isset($offerRows[$offerId])) {
                continue;
            }

            $offerRows[$offerId]['total']++;
            if ($normalizedStatus === 'SUBMITTED') {
                $offerRows[$offerId]['submitted']++;
            } elseif ($normalizedStatus === 'SHORTLISTED') {
                $offerRows[$offerId]['shortlisted']++;
            } elseif ($normalizedStatus === 'REJECTED') {
                $offerRows[$offerId]['rejected']++;
            } elseif ($normalizedStatus === 'INTERVIEW') {
                $offerRows[$offerId]['interview']++;
            } elseif ($normalizedStatus === 'HIRED') {
                $offerRows[$offerId]['hired']++;
            }
        }

        foreach ($offerRows as &$row) {
            $row['acceptance_rate'] = $row['total'] > 0
                ? (int) round((($row['shortlisted'] + $row['hired']) / $row['total']) * 100)
                : 0;
        }
        unset($row);

        $global['acceptance_rate'] = $global['total'] > 0
            ? (int) round((($global['shortlisted'] + $global['hired']) / $global['total']) * 100)
            : 0;

        return $this->render('admin/application_statistics.html.twig', [
            'global' => $global,
            'offerRows' => array_values($offerRows),
            'authUser' => ['role' => 'admin'],
        ]);
    }

    #[Route('/applicationmanagement/admin/job-applications', name: 'management_job_applications')]
    public function index(Request $request, Job_applicationRepository $jobApplicationRepository): Response
    {
        $filters = $this->normalizeDashboardFilters($request);

        $applications = $jobApplicationRepository->findForAdminDashboard($filters['q'], $filters['status'], $filters['sort']);
        $rows = $this->buildAdminApplicationRows($applications);

        return $this->render('admin/applications.html.twig', [
            'rows' => $rows,
            'filters' => $filters,
            'data_url' => $this->generateUrl('management_job_applications_data'),
            'authUser' => ['role' => 'admin'],
        ]);
    }

    #[Route('/applicationmanagement/admin/job-applications/data', name: 'management_job_applications_data', methods: ['GET'])]
    public function data(Request $request, Job_applicationRepository $jobApplicationRepository): JsonResponse
    {
        $filters = $this->normalizeDashboardFilters($request);
        $applications = $jobApplicationRepository->findForAdminDashboard($filters['q'], $filters['status'], $filters['sort']);
        $rows = $this->buildAdminApplicationRows($applications);

        $rowsHtml = $this->renderView('admin/_application_rows.html.twig', [
            'rows' => $rows,
        ]);

        return $this->json([
            'count' => count($rows),
            'rowsHtml' => $rowsHtml,
        ]);
    }

    /**
     * @param Job_application[] $applications
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildAdminApplicationRows(array $applications): array
    {
        $rows = [];

        foreach ($applications as $application) {
            $offer = $application->getOffer_id();
            $candidate = $application->getCandidate_id();
            $candidateName = $this->resolveCandidateDisplayName($candidate);

            $rows[] = [
                'id' => $application->getId(),
                'offer_title' => $offer ? $offer->getTitle() : 'Unknown Offer',
                'candidate_name' => $candidateName,
                'phone' => $application->getPhone(),
                'status' => $application->getCurrent_status(),
                'applied_at' => $application->getApplied_at(),
                'is_archived' => $application->getIs_archived(),
                'details_url' => $this->generateUrl('management_job_applications_details', ['applicationId' => $application->getId()]),
                'archive_url' => $this->generateUrl('management_job_applications_archive', ['applicationId' => $application->getId()]),
                'unarchive_url' => $this->generateUrl('management_job_applications_unarchive', ['applicationId' => $application->getId()]),
            ];
        }

        return $rows;
    }

    private function resolveCandidateDisplayName(mixed $candidate): string
    {
        if (!is_object($candidate)) {
            return 'Candidate';
        }

        $firstName = method_exists($candidate, 'getFirstName')
            ? trim((string) $candidate->getFirstName())
            : '';
        $lastName = method_exists($candidate, 'getLastName')
            ? trim((string) $candidate->getLastName())
            : '';
        $email = method_exists($candidate, 'getEmail')
            ? trim((string) $candidate->getEmail())
            : '';
        $candidateId = method_exists($candidate, 'getId')
            ? trim((string) $candidate->getId())
            : '';

        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName !== '') {
            $normalized = strtolower($fullName);

            if ($email !== '' && in_array($normalized, ['candidate user', 'candidate'], true)) {
                return $fullName . ' (' . $email . ')';
            }

            return $fullName;
        }

        if ($email !== '') {
            return $email;
        }

        if ($candidateId !== '') {
            return 'Candidate #' . $candidateId;
        }

        return 'Candidate';
    }

    /**
     * @return array{q: string, status: string, sort: string}
     */
    private function normalizeDashboardFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => strtolower(trim((string) $request->query->get('status', 'all'))),
            'sort' => strtolower(trim((string) $request->query->get('sort', 'default'))),
        ];
    }

    #[Route('/applicationmanagement/admin/job-applications/{applicationId}/details', name: 'management_job_applications_details')]
    public function details(int $applicationId, EntityManagerInterface $em): Response
    {
        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$application) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('management_job_applications');
        }

        $historyEntries = $em->getRepository(Application_status_history::class)->findBy(
            ['application_id' => $application],
            ['changed_at' => 'DESC']
        );

        $statusOptions = [
            ['value' => 'SUBMITTED', 'label' => 'Submitted'],
            ['value' => 'IN_REVIEW', 'label' => 'In Review'],
            ['value' => 'SHORTLISTED', 'label' => 'Shortlisted'],
            ['value' => 'REJECTED', 'label' => 'Rejected'],
            ['value' => 'INTERVIEW', 'label' => 'Interview'],
            ['value' => 'HIRED', 'label' => 'Hired'],
        ];

        return $this->render('admin/application_details.html.twig', [
            'application' => $application,
            'offer' => $application->getOffer_id(),
            'candidate' => $application->getCandidate_id(),
            'historyEntries' => $historyEntries,
            'statusOptions' => $statusOptions,
            'archiveUrl' => $this->generateUrl('management_job_applications_archive', ['applicationId' => $application->getId()]),
            'unarchiveUrl' => $this->generateUrl('management_job_applications_unarchive', ['applicationId' => $application->getId()]),
            'authUser' => ['role' => 'admin'],
        ]);
    }

    #[Route('/applicationmanagement/admin/job-applications/{applicationId}/archive', name: 'management_job_applications_archive', methods: ['POST'])]
    public function archive(int $applicationId, Request $request, EntityManagerInterface $em, Job_applicationRepository $jobApplicationRepository): Response
    {
        $admin = $em->getRepository(Admin::class)->find(1);
        $result = $jobApplicationRepository->archiveById($applicationId, $admin);

        if ($result === 'not_found') {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('management_job_applications');
        }

        if ($result === 'already_archived') {
            $this->addFlash('warning', 'Application is already archived.');

            return $this->redirectToRoute('management_job_applications');
        }

        $this->addFlash('success', 'Application archived successfully.');

        $returnTo = strtolower(trim((string) $request->request->get('return_to', 'list')));
        if ($returnTo === 'details') {
            return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
        }

        return $this->redirectToRoute('management_job_applications');
    }

    #[Route('/applicationmanagement/admin/job-applications/{applicationId}/unarchive', name: 'management_job_applications_unarchive', methods: ['POST'])]
    public function unarchive(int $applicationId, Request $request, EntityManagerInterface $em, Job_applicationRepository $jobApplicationRepository): Response
    {
        $admin = $em->getRepository(Admin::class)->find(1);
        $result = $jobApplicationRepository->unarchiveById($applicationId, $admin);

        if ($result === 'not_found') {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('management_job_applications');
        }

        if ($result === 'already_unarchived') {
            $this->addFlash('warning', 'Application is not archived.');

            return $this->redirectToRoute('management_job_applications');
        }

        $this->addFlash('success', 'Application unarchived successfully.');

        $returnTo = strtolower(trim((string) $request->request->get('return_to', 'list')));
        if ($returnTo === 'details') {
            return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
        }

        return $this->redirectToRoute('management_job_applications');
    }

    #[Route('/applicationmanagement/admin/job-applications/{applicationId}/status', name: 'management_job_applications_update_status', methods: ['POST'])]
    public function updateStatus(int $applicationId, Request $request, EntityManagerInterface $em): Response
    {
        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$application) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('management_job_applications');
        }

        $newStatus = strtoupper(trim((string) $request->request->get('status', '')));
        if (!in_array($newStatus, self::APPLICATION_STATUSES, true)) {
            $this->addFlash('error', 'Invalid status selected.');

            return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
        }

        $oldStatus = strtoupper(trim((string) $application->getCurrent_status()));
        $application->setCurrent_status($newStatus);

        $note = trim((string) $request->request->get('note', ''));
        if ($note === '') {
            $note = $oldStatus === $newStatus
                ? 'Admin reviewed the application and kept status as ' . $newStatus . '.'
                : 'Admin changed status from ' . $oldStatus . ' to ' . $newStatus . '.';
        }

        $admin = $em->getRepository(Admin::class)->find(1);
        if ($admin && $admin->getId()) {
            $history = new Application_status_history();
            $history->setApplication_id($application);
            $history->setStatus($newStatus);
            $history->setChanged_at(new \DateTime());
            $history->setChanged_by($admin);
            $history->setNote($note);
            $em->persist($history);
        }

        $em->flush();
        $this->addFlash('success', 'Application status updated successfully.');

        return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
    }

    #[Route('/applicationmanagement/admin/job-applications/{applicationId}/history/{historyId}/note', name: 'management_job_applications_history_note_update', methods: ['POST'])]
    public function updateHistoryNote(int $applicationId, int $historyId, Request $request, EntityManagerInterface $em): Response
    {
        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$application) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('management_job_applications');
        }

        $history = $em->getRepository(Application_status_history::class)->find($historyId);
        if (!$history || $history->getApplication_id() !== $application) {
            $this->addFlash('error', 'History entry not found.');

            return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
        }

        $currentNote = trim((string) $history->getNote());
        $newNote = trim((string) $request->request->get('note', ''));
        if ($newNote === '') {
            $this->addFlash('warning', 'Note cannot be empty.');

            return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
        }

        if ($newNote === $currentNote) {
            $this->addFlash('info', 'No changes detected in the note.');

            return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
        }

        $history->setNote($newNote);
        $em->flush();
        $this->addFlash('success', 'Status history note updated.');

        return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
    }

    #[Route('/applicationmanagement/admin/job-applications/{applicationId}/history/{historyId}/delete', name: 'management_job_applications_history_delete', methods: ['POST'])]
    public function deleteHistory(int $applicationId, int $historyId, EntityManagerInterface $em): Response
    {
        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$application) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('management_job_applications');
        }

        $history = $em->getRepository(Application_status_history::class)->find($historyId);
        if (!$history || $history->getApplication_id() !== $application) {
            $this->addFlash('error', 'History entry not found.');

            return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
        }

        $em->remove($history);
        $em->flush();
        $this->addFlash('success', 'History entry deleted.');

        return $this->redirectToRoute('management_job_applications_details', ['applicationId' => $applicationId]);
    }
}
