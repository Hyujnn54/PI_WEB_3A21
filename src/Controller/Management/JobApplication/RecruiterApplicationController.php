<?php

namespace App\Controller\Management\JobApplication;

use App\Entity\Application_status_history;
use App\Entity\Job_application;
use App\Entity\Recruiter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/applicationmanagement/recruiter/applications/{applicationId}/details', name: 'app_recruiter_application_details')]
    public function details(int $applicationId, Request $request, EntityManagerInterface $em): Response
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

        $historyEntries = $em->getRepository(Application_status_history::class)->findBy(
            ['application_id' => $application],
            ['changed_at' => 'DESC']
        );

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

    #[Route('/applicationmanagement/recruiter/applications/{applicationId}/history/{historyId}/note', name: 'app_recruiter_history_note_update', methods: ['POST'])]
    public function updateHistoryNote(
        int $applicationId,
        int $historyId,
        Request $request,
        EntityManagerInterface $em
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

        $history = $em->getRepository(Application_status_history::class)->find($historyId);
        if (!$history || $history->getApplication_id() !== $application) {
            $this->addFlash('error', 'History entry not found.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        if (!$this->isHistoryEditableByRecruiter($history, $recruiter)) {
            $this->addFlash('warning', 'You can edit only your own manually written notes.');

            return $this->redirectToRoute('app_recruiter_application_details', ['applicationId' => $applicationId]);
        }

        $newNote = trim((string) $request->request->get('note', ''));
        if ($newNote === '') {
            $this->addFlash('warning', 'Note cannot be empty.');

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
}
