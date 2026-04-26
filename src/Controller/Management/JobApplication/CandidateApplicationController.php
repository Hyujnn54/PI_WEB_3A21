<?php

namespace App\Controller\Management\JobApplication;

use App\Entity\Application_status_history;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Candidate;
use App\Form\JobApplicationType;
use App\Repository\Application_status_historyRepository;
use App\Repository\Candidate_skillRepository;
use App\Repository\Job_applicationRepository;
use App\Service\JobApplication\GroqCoverLetterGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CandidateApplicationController extends AbstractController
{
    #[Route('/applicationmanagement/candidate/offers/{offerId}/cover-letter/generate', name: 'app_candidate_cover_letter_generate', methods: ['POST'])]
    public function generateCoverLetter(
        int $offerId,
        Request $request,
        EntityManagerInterface $em,
        Candidate_skillRepository $candidateSkillRepository,
        GroqCoverLetterGenerator $groqCoverLetterGenerator,
        Job_applicationRepository $jobApplicationRepository
    ): JsonResponse {
        if (!$this->isCandidateContext($request)) {
            return $this->json([
                'ok' => false,
                'error' => 'Only candidates can generate a cover letter.',
            ], Response::HTTP_FORBIDDEN);
        }

        $candidate = $this->resolveCurrentCandidate($request, $em);
        $offer = $em->getRepository(Job_offer::class)->find($offerId);

        if (!$candidate || !$offer) {
            return $this->json([
                'ok' => false,
                'error' => 'Candidate or offer not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $useProfileCv = filter_var((string) $request->request->get('use_profile_cv', '0'), FILTER_VALIDATE_BOOL);
        $uploadedCv = $request->files->get('cv_file');
        $applicationId = (int) $request->request->get('application_id', 0);
        $profileCvPath = method_exists($candidate, 'getCvPath') ? trim((string) $candidate->getCvPath()) : '';
        $applicationCvPath = '';

        if ($useProfileCv && $profileCvPath === '') {
            return $this->json([
                'ok' => false,
                'error' => 'No CV found in your profile. Uncheck "Use CV from profile", upload a CV, then generate the cover letter.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$useProfileCv && !($uploadedCv instanceof UploadedFile)) {
            if ($applicationId > 0) {
                $application = $jobApplicationRepository->findForCandidate($applicationId, $candidate);
                if ($application instanceof Job_application) {
                    $applicationCvPath = trim((string) $application->getCv_path());
                }
            }

            if ($applicationCvPath !== '') {
                $cvText = $this->extractCvTextFromApplicationPath($applicationCvPath);
                if ($cvText !== '') {
                    $skills = $candidateSkillRepository->findSkillSummariesForCandidate($candidate);

                    try {
                        $coverLetter = $groqCoverLetterGenerator->generate([
                            'candidate_name' => $this->resolveCandidateDisplayName($candidate),
                            'candidate_email' => trim((string) $candidate->getEmail()),
                            'candidate_phone' => trim((string) $candidate->getPhone()),
                            'candidate_location' => trim((string) $candidate->getLocation()),
                            'education_level' => trim((string) $candidate->getEducationLevel()),
                            'experience_years' => $candidate->getExperienceYears() !== null
                                ? ((string) $candidate->getExperienceYears() . ' years')
                                : '',
                            'skills' => $skills,
                            'offer_title' => trim((string) $offer->getTitle()),
                            'offer_location' => trim((string) $offer->getLocation()),
                            'offer_contract' => trim((string) $offer->getContract_type()),
                            'cv_text' => $cvText,
                        ]);
                    } catch (\Throwable $exception) {
                        return $this->json([
                            'ok' => false,
                            'error' => $exception->getMessage(),
                        ], Response::HTTP_BAD_GATEWAY);
                    }

                    return $this->json([
                        'ok' => true,
                        'cover_letter' => $coverLetter,
                    ]);
                }
            }

            return $this->json([
                'ok' => false,
                'error' => 'Please upload a CV first, or keep an existing CV in this application, then generate the cover letter.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cvText = $useProfileCv
            ? $this->extractCvTextFromProfilePath($profileCvPath)
            : $this->extractCvTextFromUploadedFile($uploadedCv);

        if ($cvText === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Could not read text from the selected CV. Please upload a readable CV (PDF, DOCX, or TXT) and try again.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $skills = $candidateSkillRepository->findSkillSummariesForCandidate($candidate);

        try {
            $coverLetter = $groqCoverLetterGenerator->generate([
                'candidate_name' => $this->resolveCandidateDisplayName($candidate),
                'candidate_email' => trim((string) $candidate->getEmail()),
                'candidate_phone' => trim((string) $candidate->getPhone()),
                'candidate_location' => trim((string) $candidate->getLocation()),
                'education_level' => trim((string) $candidate->getEducationLevel()),
                'experience_years' => $candidate->getExperienceYears() !== null
                    ? ((string) $candidate->getExperienceYears() . ' years')
                    : '',
                'skills' => $skills,
                'offer_title' => trim((string) $offer->getTitle()),
                'offer_location' => trim((string) $offer->getLocation()),
                'offer_contract' => trim((string) $offer->getContract_type()),
                'cv_text' => $cvText,
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'ok' => true,
            'cover_letter' => $coverLetter,
        ]);
    }

    #[Route('/applicationmanagement/candidate/apply/{offerId}', name: 'app_candidate_apply')]
    public function apply(
        int $offerId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        Job_applicationRepository $jobApplicationRepository,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        if (!$this->isCandidateContext($request)) {
            $this->addFlash('warning', 'Only candidates can apply for job offers.');
            return $this->redirectToRoute('front_job_offers');
        }

        // Fetch the candidate and the job offer
        $candidate = $this->resolveCurrentCandidate($request, $em);
        $jobOffer = $em->getRepository(Job_offer::class)->find($offerId);

        if (!$candidate || !$jobOffer) {
            return new Response('Candidate or Job Offer not found!', Response::HTTP_NOT_FOUND);
        }

        $offerStatus = strtolower(trim((string) $jobOffer->getStatus()));
        $offerDeadline = $jobOffer->getDeadline();
        $isExpired = $offerDeadline instanceof \DateTimeInterface && $offerDeadline < new \DateTimeImmutable();
        if ($offerStatus !== 'open' || $isExpired) {
            $this->addFlash('warning', 'This offer is closed and no longer accepts applications.');
            return $this->redirectToRoute('front_job_offers', ['role' => 'candidate']);
        }

        $existingApplication = $jobApplicationRepository->findActiveByOfferAndCandidate($jobOffer, $candidate);

        if ($existingApplication) {
            $this->addFlash('warning', 'You have already applied for this job offer.');

            return $this->redirectToRoute('front_job_offers', ['role' => 'candidate']);
        }

        $application = new Job_application();
        $application->setOffer_id($jobOffer);
        $application->setCandidate_id($candidate);
        
        // Pre-fill phone if available on candidate profile (assuming string)
        if (method_exists($candidate, 'getPhone') && $candidate->getPhone()) {
            $application->setPhone($candidate->getPhone());
        }

        $form = $this->createForm(JobApplicationType::class, $application);
        if (!$request->isMethod('POST')) {
            $form->get('use_profile_cv')->setData(true);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $useProfileCv = $form->get('use_profile_cv')->getData();
            $cvFile = $form->get('cv_file')->getData();

            if (!$this->verifyApplyFormInController($form, $application, $useProfileCv, $cvFile, $candidate, true)) {
                return $this->render('management/job_application/apply.html.twig', [
                    'form' => $form->createView(),
                    'offer' => $jobOffer,
                    'candidate' => $candidate,
                ]);
            }

            if ($useProfileCv) {
                $profileCvPath = method_exists($candidate, 'getCvPath') ? $candidate->getCvPath() : null;
                if (empty($profileCvPath)) {
                    $form->get('use_profile_cv')->addError(new FormError('No CV found in your profile. Uncheck this option and upload a CV.'));

                    return $this->render('management/job_application/apply.html.twig', [
                        'form' => $form->createView(),
                        'offer' => $jobOffer,
                        'candidate' => $candidate,
                    ]);
                }

                $application->setCv_path($profileCvPath);
            } elseif ($cvFile) {
                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

                try {
                    $uploadPath = $this->getParameter('kernel.project_dir') . '/public/uploads/applications';
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    $cvFile->move($uploadPath, $newFilename);
                    $application->setCv_path($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'CV upload failed. Please try again.');

                    return $this->render('management/job_application/apply.html.twig', [
                        'form' => $form->createView(),
                        'offer' => $jobOffer,
                        'candidate' => $candidate,
                    ]);
                }
            } else {
                $form->get('cv_file')->addError(new FormError('Please upload a CV file or choose the profile CV option.'));

                return $this->render('management/job_application/apply.html.twig', [
                    'form' => $form->createView(),
                    'offer' => $jobOffer,
                    'candidate' => $candidate,
                ]);
            }

            // Default properties
            $application->setApplied_at(new \DateTime());
            $application->setCurrent_status('SUBMITTED');
            $application->setIs_archived(false);

            $candidateName = $this->resolveCandidateDisplayName($candidate);
            $applyNote = $candidateName . ' submitted a new application.';
            $this->logStatusHistory($em, $application, $candidate, $applyNote);

            $em->persist($application);
            $em->flush();

            $this->sendApplicationConfirmationEmail($mailer, $logger, $candidate, $jobOffer, $application);

            return $this->redirectToRoute('front_job_offers', ['role' => 'candidate']);
        }

        return $this->render('management/job_application/apply.html.twig', [
            'form' => $form->createView(),
            'offer' => $jobOffer,
            'candidate' => $candidate
        ]);

    }

    #[Route('/applicationmanagement/candidate/applications/{applicationId}/withdraw', name: 'app_candidate_application_withdraw', methods: ['POST'])]
    public function withdraw(
        int $applicationId,
        Request $request,
        EntityManagerInterface $em,
        Job_applicationRepository $jobApplicationRepository
    ): Response {
        if (!$this->isCandidateContext($request)) {
            $this->addFlash('warning', 'Only candidates can manage candidate applications.');
            return $this->redirectToRoute('front_job_offers');
        }

        $candidate = $this->resolveCurrentCandidate($request, $em);
        if (!$candidate) {
            $this->addFlash('error', 'Candidate not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $application = $jobApplicationRepository->findForCandidate($applicationId, $candidate);
        if (!$application) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $status = strtoupper(trim((string) $application->getCurrent_status()));
        if ($status !== 'SUBMITTED') {
            $this->addFlash('warning', 'Only applications with SUBMITTED status can be withdrawn.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $em->remove($application);
        $em->flush();

        $this->addFlash('success', 'Application withdrawn successfully.');

        return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
    }

    #[Route('/applicationmanagement/candidate/applications/{applicationId}/details', name: 'app_candidate_application_details')]
    public function details(
        int $applicationId,
        Request $request,
        EntityManagerInterface $em,
        Job_applicationRepository $jobApplicationRepository,
        Application_status_historyRepository $historyRepository
    ): Response {
        if (!$this->isCandidateContext($request)) {
            $this->addFlash('warning', 'Only candidates can access candidate application details.');
            return $this->redirectToRoute('front_job_offers');
        }

        $candidate = $this->resolveCurrentCandidate($request, $em);
        if (!$candidate) {
            $this->addFlash('error', 'Candidate not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $application = $jobApplicationRepository->findForCandidate($applicationId, $candidate);
        if (!$application) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $historyEntries = $historyRepository->findForApplication($application);

        return $this->render('management/job_application/details.html.twig', [
            'application' => $application,
            'offer' => $application->getOffer_id(),
            'candidate' => $candidate,
            'historyEntries' => $historyEntries,
        ]);
    }

    #[Route('/applicationmanagement/candidate/applications/{applicationId}/edit', name: 'app_candidate_application_edit')]
    public function edit(
        int $applicationId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        Job_applicationRepository $jobApplicationRepository
    ): Response {
        if (!$this->isCandidateContext($request)) {
            $this->addFlash('warning', 'Only candidates can edit candidate applications.');
            return $this->redirectToRoute('front_job_offers');
        }

        $candidate = $this->resolveCurrentCandidate($request, $em);
        if (!$candidate) {
            $this->addFlash('error', 'Candidate not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $application = $jobApplicationRepository->findForCandidate($applicationId, $candidate);
        if (!$application) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $status = strtoupper(trim((string) $application->getCurrent_status()));
        if ($status !== 'SUBMITTED') {
            $this->addFlash('warning', 'Only applications with SUBMITTED status can be edited.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $form = $this->createForm(JobApplicationType::class, $application);
        $originalPhone = (string) $application->getPhone();
        $originalCoverLetter = (string) $application->getCover_letter();
        $originalCvPath = (string) $application->getCv_path();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $useProfileCv = $form->get('use_profile_cv')->getData();
            $cvFile = $form->get('cv_file')->getData();

            if (!$this->verifyApplyFormInController($form, $application, $useProfileCv, $cvFile, $candidate, false)) {
                return $this->render('management/job_application/edit.html.twig', [
                    'form' => $form->createView(),
                    'application' => $application,
                    'offer' => $application->getOffer_id(),
                    'candidate' => $candidate,
                ]);
            }

            if ($useProfileCv) {
                $profileCvPath = method_exists($candidate, 'getCvPath') ? $candidate->getCvPath() : null;
                if (empty($profileCvPath)) {
                    $form->get('use_profile_cv')->addError(new FormError('No CV found in your profile. Uncheck this option and upload a CV.'));

                    return $this->render('management/job_application/edit.html.twig', [
                        'form' => $form->createView(),
                        'application' => $application,
                        'offer' => $application->getOffer_id(),
                        'candidate' => $candidate,
                    ]);
                }

                $application->setCv_path($profileCvPath);
            } elseif ($cvFile) {
                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

                try {
                    $uploadPath = $this->getParameter('kernel.project_dir') . '/public/uploads/applications';
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    $cvFile->move($uploadPath, $newFilename);
                    $application->setCv_path($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'CV upload failed. Please try again.');

                    return $this->render('management/job_application/edit.html.twig', [
                        'form' => $form->createView(),
                        'application' => $application,
                        'offer' => $application->getOffer_id(),
                        'candidate' => $candidate,
                    ]);
                }
            }

            $changes = [];
            if ($originalPhone !== (string) $application->getPhone()) {
                $changes[] = 'phone number';
            }

            if ($originalCoverLetter !== (string) $application->getCover_letter()) {
                $changes[] = 'cover letter';
            }

            if ($originalCvPath !== (string) $application->getCv_path()) {
                $changes[] = 'CV';
            }

            $candidateName = $this->resolveCandidateDisplayName($candidate);
            $note = empty($changes)
                ? $candidateName . ' updated the application.'
                : $candidateName . ' updated the application: changed ' . implode(', ', $changes) . '.';

            $this->logStatusHistory($em, $application, $candidate, $note);

            $em->flush();
            $this->addFlash('success', 'Application updated successfully.');

            return $this->redirectToRoute('app_candidate_application_details', ['applicationId' => $application->getId()]);
        }

        return $this->render('management/job_application/edit.html.twig', [
            'form' => $form->createView(),
            'application' => $application,
            'offer' => $application->getOffer_id(),
            'candidate' => $candidate,
        ]);
    }

    private function isCandidateContext(Request $request): bool
    {
        if ($this->isGranted('ROLE_CANDIDATE')) {
            return true;
        }

        $roles = (array) $request->getSession()->get('user_roles', []);

        return in_array('ROLE_CANDIDATE', $roles, true);
    }

    private function resolveCurrentCandidate(Request $request, EntityManagerInterface $em): ?Candidate
    {
        $user = $this->getUser();
        if ($user instanceof Candidate) {
            return $user;
        }

        $userId = '';
        if (is_object($user) && method_exists($user, 'getId')) {
            $userId = (string) $user->getId();
        }

        if ($userId === '') {
            $userId = (string) $request->getSession()->get('user_id', '');
        }

        if ($userId === '') {
            return null;
        }

        $candidate = $em->getRepository(Candidate::class)->find($userId);

        return $candidate instanceof Candidate ? $candidate : null;
    }

    private function verifyApplyFormInController(
        FormInterface $form,
        Job_application $application,
        bool $useProfileCv,
        ?UploadedFile $cvFile,
        Candidate $candidate,
        bool $requireCvSource
    ): bool {
        $isValid = true;

        $phone = trim((string) $application->getPhone());
        $localPhone = $this->extractTunisianLocalNumber($phone);
        if (!preg_match('/^[259][0-9]{7}$/', $localPhone)) {
            $form->get('phone')->addError(new FormError('Please enter a valid Tunisian phone number (+216XXXXXXXX, 216XXXXXXXX, 0XXXXXXXX or XXXXXXXX).'));
            $isValid = false;
        } else {
            $application->setPhone('+216' . $localPhone);
        }

        $coverLetter = trim((string) $application->getCover_letter());
        $coverLetterLength = mb_strlen($coverLetter);
        if ($coverLetterLength < 50 || $coverLetterLength > 2000) {
            $form->get('cover_letter')->addError(new FormError('Cover letter must be between 50 and 2000 characters.'));
            $isValid = false;
        } else {
            $application->setCover_letter($coverLetter);
        }

        if ($useProfileCv) {
            $profileCvPath = method_exists($candidate, 'getCvPath') ? (string) $candidate->getCvPath() : '';
            if ($profileCvPath === '') {
                $form->get('use_profile_cv')->addError(new FormError('No CV found in your profile. Uncheck this option and upload a CV.'));
                $isValid = false;
            }
        } else {
            $existingCvPath = trim((string) $application->getCv_path());
            if (!$cvFile instanceof UploadedFile && ($requireCvSource || $existingCvPath === '')) {
                $form->get('cv_file')->addError(new FormError('Please upload a CV file or choose the profile CV option.'));
                $isValid = false;
            }
        }

        return $isValid;
    }

    private function extractTunisianLocalNumber(string $value): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $value) ?? '';

        if (str_starts_with($cleaned, '+216') && strlen($cleaned) === 12) {
            return substr($cleaned, 4);
        }

        if (str_starts_with($cleaned, '216') && strlen($cleaned) === 11) {
            return substr($cleaned, 3);
        }

        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 9) {
            return substr($cleaned, 1);
        }

        if (strlen($cleaned) === 8 && ctype_digit($cleaned)) {
            return $cleaned;
        }

        return '';
    }

    private function logStatusHistory(
        EntityManagerInterface $em,
        Job_application $application,
        Candidate $candidate,
        string $note
    ): void {
        $history = new Application_status_history();
        $history->setApplication_id($application);
        $history->setStatus((string) $application->getCurrent_status());
        $history->setChanged_at(new \DateTime());
        $history->setChanged_by($candidate);

        $history->setNote($note);
        $em->persist($history);
    }

    private function resolveCandidateDisplayName(Candidate $candidate): string
    {
        $firstName = (string) $candidate->getFirstName();
        $lastName = (string) $candidate->getLastName();
        $fullName = trim($firstName . ' ' . $lastName);

        return $fullName !== '' ? $fullName : 'Candidate';
    }

    private function sendApplicationConfirmationEmail(
        MailerInterface $mailer,
        LoggerInterface $logger,
        Candidate $candidate,
        Job_offer $jobOffer,
        Job_application $application
    ): void {
        $recipient = trim((string) $candidate->getEmail());
        if ($recipient === '') {
            return;
        }

        $fromAddress = trim((string) ($_ENV['MAILER_FROM_ADDRESS'] ?? $_SERVER['MAILER_FROM_ADDRESS'] ?? 'no-reply@talent-bridge.local'));
        $fromName = trim((string) ($_ENV['MAILER_FROM_NAME'] ?? $_SERVER['MAILER_FROM_NAME'] ?? 'Talent Bridge Recrutement'));
        $candidateName = $this->resolveCandidateDisplayName($candidate);
        $offerTitle = trim((string) $jobOffer->getTitle());
        $appliedAt = $application->getApplied_at();
        $appliedAtText = $appliedAt instanceof \DateTimeInterface
            ? $appliedAt->format('F j, Y \\a\\t H:i')
            : (new \DateTimeImmutable())->format('F j, Y \\a\\t H:i');

        $body = $this->renderView('emails/application_confirmation.txt.twig', [
            'candidateName' => $candidateName,
            'offerTitle' => $offerTitle,
            'appliedAtText' => $appliedAtText,
        ]);

        try {
            $email = (new Email())
                ->from(new Address($fromAddress, $fromName))
                ->to($recipient)
                ->subject(sprintf('Application received for "%s"', $offerTitle !== '' ? $offerTitle : 'your selected position'))
                ->text($body);

            $mailer->send($email);
        } catch (\Throwable $exception) {
            $logger->error('Candidate application confirmation email failed to send.', [
                'candidate_id' => $candidate->getId(),
                'candidate_email' => $recipient,
                'offer_id' => $jobOffer->getId(),
                'offer_title' => $offerTitle,
                'error_message' => $exception->getMessage(),
            ]);

            $appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev'));
            if ($appEnv === 'dev') {
                $this->addFlash('warning', 'Your application was submitted, but the confirmation email failed: ' . $exception->getMessage());
                return;
            }

            $this->addFlash('warning', 'Your application was submitted, but we could not send the confirmation email right now.');
        }
    }

    private function extractCvTextFromProfilePath(string $profileCvPath): string
    {
        $fileName = basename(trim($profileCvPath));
        if ($fileName === '') {
            return '';
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $candidatePaths = [
            $projectDir . '/public/uploads/cvs/' . $fileName,
            $projectDir . '/public/uploads/applications/' . $fileName,
            $projectDir . '/public/' . $fileName,
        ];

        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                return $this->extractReadableTextFromFile($path, $fileName);
            }
        }

        return '';
    }

    private function extractCvTextFromApplicationPath(string $applicationCvPath): string
    {
        $fileName = basename(trim($applicationCvPath));
        if ($fileName === '') {
            return '';
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $candidatePaths = [
            $projectDir . '/public/uploads/applications/' . $fileName,
            $projectDir . '/public/uploads/cvs/' . $fileName,
            $projectDir . '/public/' . $fileName,
        ];

        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                return $this->extractReadableTextFromFile($path, $fileName);
            }
        }

        return '';
    }

    private function extractCvTextFromUploadedFile(?UploadedFile $uploadedCv): string
    {
        if (!$uploadedCv instanceof UploadedFile) {
            return '';
        }

        $realPath = $uploadedCv->getRealPath();
        if (!is_string($realPath) || $realPath === '') {
            $realPath = $uploadedCv->getPathname();
        }

        return $this->extractReadableTextFromFile($realPath, (string) $uploadedCv->getClientOriginalName());
    }

    private function extractReadableTextFromFile(string $absolutePath, string $fileName): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return '';
        }

        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $rawContent = file_get_contents($absolutePath);
        if ($rawContent === false || $rawContent === '') {
            return '';
        }

        if (in_array($extension, ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm'], true)) {
            return $this->normalizeCvText($rawContent);
        }

        if ($extension === 'docx' && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($absolutePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if (is_string($xml) && $xml !== '') {
                    $text = strip_tags($xml);

                    return $this->normalizeCvText($text);
                }
            }
        }

        if ($extension === 'pdf') {
            return $this->extractPdfText($rawContent);
        }

        return $this->normalizeCvText($rawContent);
    }

    private function extractPdfText(string $rawPdf): string
    {
        $chunks = [];
        if (preg_match_all('/\(([^()]*)\)/s', $rawPdf, $matches) > 0 && isset($matches[1])) {
            foreach ($matches[1] as $chunk) {
                $cleanChunk = preg_replace('/\\\\[nrt]/', ' ', (string) $chunk);
                $cleanChunk = preg_replace('/\\\\\d{3}/', ' ', (string) $cleanChunk);
                if (!is_string($cleanChunk)) {
                    continue;
                }
                $chunks[] = $cleanChunk;
            }
        }

        if ($chunks === []) {
            return '';
        }

        return $this->normalizeCvText(implode(' ', $chunks));
    }

    private function normalizeCvText(string $rawText): string
    {
        $normalized = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ' '], $rawText);
        $normalized = strip_tags($normalized);
        $normalized = preg_replace('/[^\PC\n\t]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, 7000);
    }
}

