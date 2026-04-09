<?php

namespace App\Controller\Management\JobApplication;

use App\Entity\Application_status_history;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Candidate;
use App\Form\JobApplicationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Form\FormError;

class CandidateApplicationController extends AbstractController
{
    #[Route('/applicationmanagement/candidate/apply/{offerId}', name: 'app_candidate_apply')]
    public function apply(
        int $offerId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $roles = (array) $request->getSession()->get('user_roles', []);
        if (!in_array('ROLE_CANDIDATE', $roles, true)) {
            $this->addFlash('warning', 'Only candidates can apply for job offers.');
            return $this->redirectToRoute('front_job_offers');
        }

        $candidateId = (string) $request->getSession()->get('user_id', '');

        // Fetch the candidate and the job offer
        $candidate = $em->getRepository(Candidate::class)->find($candidateId);
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

        $existingApplication = $em->getRepository(Job_application::class)->findOneBy([
            'offer_id' => $jobOffer,
            'candidate_id' => $candidate,
            'is_archived' => false,
        ]);

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

            if ($useProfileCv) {
                $profileCvPath = method_exists($candidate, 'getCv_path') ? $candidate->getCv_path() : null;
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
        EntityManagerInterface $em
    ): Response {
        $roles = (array) $request->getSession()->get('user_roles', []);
        if (!in_array('ROLE_CANDIDATE', $roles, true)) {
            $this->addFlash('warning', 'Only candidates can manage candidate applications.');
            return $this->redirectToRoute('front_job_offers');
        }

        $candidateId = (string) $request->getSession()->get('user_id', '');

        $candidate = $em->getRepository(Candidate::class)->find($candidateId);
        if (!$candidate) {
            $this->addFlash('error', 'Candidate not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$application || $application->getCandidate_id() !== $candidate) {
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
        EntityManagerInterface $em
    ): Response {
        $roles = (array) $request->getSession()->get('user_roles', []);
        if (!in_array('ROLE_CANDIDATE', $roles, true)) {
            $this->addFlash('warning', 'Only candidates can access candidate application details.');
            return $this->redirectToRoute('front_job_offers');
        }

        $candidateId = (string) $request->getSession()->get('user_id', '');

        $candidate = $em->getRepository(Candidate::class)->find($candidateId);
        if (!$candidate) {
            $this->addFlash('error', 'Candidate not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$application || $application->getCandidate_id() !== $candidate) {
            $this->addFlash('error', 'Application not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $historyEntries = $em->getRepository(Application_status_history::class)->findBy(
            ['application_id' => $application],
            ['changed_at' => 'DESC']
        );

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
        SluggerInterface $slugger
    ): Response {
        $roles = (array) $request->getSession()->get('user_roles', []);
        if (!in_array('ROLE_CANDIDATE', $roles, true)) {
            $this->addFlash('warning', 'Only candidates can edit candidate applications.');
            return $this->redirectToRoute('front_job_offers');
        }

        $candidateId = (string) $request->getSession()->get('user_id', '');

        $candidate = $em->getRepository(Candidate::class)->find($candidateId);
        if (!$candidate) {
            $this->addFlash('error', 'Candidate not found.');

            return $this->redirectToRoute('front_job_applications', ['role' => 'candidate']);
        }

        $application = $em->getRepository(Job_application::class)->find($applicationId);
        if (!$application || $application->getCandidate_id() !== $candidate) {
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

            if ($useProfileCv) {
                $profileCvPath = method_exists($candidate, 'getCv_path') ? $candidate->getCv_path() : null;
                if (empty($profileCvPath)) {
                    $form->get('use_profile_cv')->addError(new FormError('No CV found in your profile. Uncheck this option and upload a CV.'));

                    return $this->render('management/job_application/edit.html.twig', [
                        'form' => $form->createView(),
                        'application' => $application,
                        'offer' => $application->getOffer_id(),
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
        ]);
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
}

