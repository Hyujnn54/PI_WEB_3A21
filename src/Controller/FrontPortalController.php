<?php

namespace App\Controller;

use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use App\Form\JobOfferType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class FrontPortalController extends AbstractController
{
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
                'id' => $offer->getId(),
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

        $cards = array_map(static function (Job_application $application): array {
            $offer = $application->getOffer_id();
            $coverLetter = trim((string) $application->getCover_letter());

            return [
                'meta' => sprintf('Application #%s | %s', (string) $application->getId(), (string) $application->getCurrent_status()),
                'title' => sprintf('Offer: %s', (string) $offer->getTitle()),
                'text' => $coverLetter === '' ? 'No cover letter provided.' : substr($coverLetter, 0, 190),
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
        foreach ($interviews as $interview) {
            try {
                $application = $interview->getApplication_id();
                $offer = $application->getOffer_id();

                $scheduledAt = $interview->getScheduled_at();
                $status = (string) $interview->getStatus();
                $title = (string) $offer->getTitle();
                $notes = trim((string) $interview->getNotes());

                $cards[] = [
                    'meta' => sprintf('%s | %s', $scheduledAt->format('d M Y | H:i'), $status === '' ? 'Pending' : $status),
                    'title' => sprintf('Interview: %s', $title === '' ? 'Untitled offer' : $title),
                    'text' => $notes === '' ? 'No interview notes available yet.' : substr($notes, 0, 190),
                ];
            } catch (Throwable) {
                // Skip malformed rows so one broken interview does not break the page.
                continue;
            }
        }

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/job-offers/new', name: 'front_job_offers_new', methods: ['GET', 'POST'])]
    public function jobOfferCreate(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        if ('recruiter' !== $role) {
            throw $this->createAccessDeniedException('Only recruiters can create job offers.');
        }
        
        $offer = new Job_offer();
        $form = $this->createForm(JobOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $offer->setCreated_at(new \DateTime());
            $offer->setStatus('Active');
            $offer->setQuality_score(100);
            $offer->setAi_suggestions('[]');
            $offer->setIs_flagged(false);
            $offer->setFlagged_at(new \DateTime());

            $recruiter = $this->doctrine->getRepository(Recruiter::class)->findOneBy([]);
            if ($recruiter) {
                $offer->setRecruiter_id($recruiter);
            }

            $entityManager = $this->doctrine->getManager();
            $entityManager->persist($offer);
            $entityManager->flush();

            $this->addFlash('success', 'Job offer created successfully!');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        return $this->render('front/modules/job_offer_form.html.twig', [
            'form' => $form->createView(),
            'authUser' => ['role' => $role],
            'action' => 'Create',
            'offer' => $offer,
        ]);
    }

    #[Route('/front/job-offers/{id}/edit', name: 'front_job_offers_edit', methods: ['GET', 'POST'])]
    public function jobOfferEdit(Request $request, Job_offer $offer): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        if ('recruiter' !== $role) {
            throw $this->createAccessDeniedException('Only recruiters can edit job offers.');
        }

        $form = $this->createForm(JobOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->doctrine->getManager()->flush();

            $this->addFlash('success', 'Job offer updated successfully!');
            return $this->redirectToRoute('front_job_offers', ['role' => $role]);
        }

        return $this->render('front/modules/job_offer_form.html.twig', [
            'form' => $form->createView(),
            'authUser' => ['role' => $role],
            'action' => 'Edit',
            'offer' => $offer,
        ]);
    }

    #[Route('/front/job-offers/{id}/delete', name: 'front_job_offers_delete', methods: ['POST'])]
    public function jobOfferDelete(Request $request, Job_offer $offer): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        if ('recruiter' !== $role) {
            throw $this->createAccessDeniedException('Only recruiters can delete job offers.');
        }

        if ($this->isCsrfTokenValid('delete' . $offer->getId(), (string) $request->request->get('_token'))) {
            $entityManager = $this->doctrine->getManager();
            $entityManager->remove($offer);
            $entityManager->flush();
            $this->addFlash('success', 'Job offer deleted successfully!');
        }

        return $this->redirectToRoute('front_job_offers', ['role' => $role]);
    }
}
