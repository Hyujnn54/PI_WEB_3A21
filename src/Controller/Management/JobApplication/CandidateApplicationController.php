<?php

namespace App\Controller\Management\JobApplication;

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

class CandidateApplicationController extends AbstractController
{
    #[Route('/applicationmanagement/candidate/apply/{offerId}', name: 'app_candidate_apply')]
    public function apply(
        int $offerId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        // As requested, hardcoding Candidate ID = 3
        $candidateId = 3;

        // Fetch the candidate and the job offer
        $candidate = $em->getRepository(Candidate::class)->find($candidateId);
        $jobOffer = $em->getRepository(Job_offer::class)->find($offerId);

        if (!$candidate || !$jobOffer) {
            return new Response('Candidate or Job Offer not found!', Response::HTTP_NOT_FOUND);
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
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $useProfileCv = $form->get('use_profile_cv')->getData();
            $cvFile = $form->get('cv_file')->getData();

            if ($useProfileCv) {
                $profileCvPath = method_exists($candidate, 'getCv_path') ? $candidate->getCv_path() : null;
                if (empty($profileCvPath)) {
                    $this->addFlash('warning', 'No CV found in your profile. Please upload a CV.');

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
                $this->addFlash('warning', 'Please choose a CV source: profile CV or upload a new one.');

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
        EntityManagerInterface $em
    ): Response {
        $candidateId = 3;

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
        EntityManagerInterface $em
    ): Response {
        $candidateId = 3;

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

        return $this->render('management/job_application/details.html.twig', [
            'application' => $application,
            'offer' => $application->getOffer_id(),
            'candidate' => $candidate,
        ]);
    }
}

