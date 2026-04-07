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
                // Suppose the candidate entity has getResume or getCvPath
                if (method_exists($candidate, 'getCv_path') && $candidate->getCv_path()) {
                    $application->setCv_path($candidate->getCv_path());
                } else {
                    // Fallback dummy
                    $application->setCv_path('profile_cv_dummy.pdf');
                }
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
                    $application->setCv_path('upload_error.pdf');
                }
            } else {
                $application->setCv_path('no_cv_provided.pdf');
            }

            // Default properties
            $application->setApplied_at(new \DateTime());
            $application->setCurrent_status('SUBMITTED');
            $application->setIs_archived(false);

            $em->persist($application);
            $em->flush();

            return $this->redirectToRoute('front_job_offers'); // Redirect back to offers after submitting
        }

        return $this->render('management/job_application/apply.html.twig', [
            'form' => $form->createView(),
            'offer' => $jobOffer,
            'candidate' => $candidate
        ]);

    }
}

