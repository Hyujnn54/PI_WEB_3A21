<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Recruitment_event;
use App\Entity\Event_registration;

class FrontPortalController extends AbstractController
{
    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['id' => 1, 'meta' => 'Tunis | CDI', 'title' => 'Frontend Engineer', 'text' => 'Build and iterate candidate-facing experiences with reusable UI modules.'],
            ['id' => 2, 'meta' => 'Sfax | CDI', 'title' => 'Symfony Backend Developer', 'text' => 'Maintain recruitment workflows and implement stable API endpoints.'],
            ['id' => 3, 'meta' => 'Remote | Contract', 'title' => 'Recruitment Data Analyst', 'text' => 'Track funnel metrics and transform hiring data into useful insights.'],
        ];

        return $this->render('front/modules/job_offers.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/job-applications', name: 'front_job_applications')]
    public function jobApplications(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => 'Application #1021 | Under Review', 'title' => 'Offer: Frontend Engineer', 'text' => 'Your profile passed initial screening and is awaiting recruiter feedback.'],
            ['meta' => 'Application #1022 | Interview Scheduled', 'title' => 'Offer: Symfony Backend Developer', 'text' => 'Technical interview is planned and pending confirmation details.'],
            ['meta' => 'Application #1023 | Accepted', 'title' => 'Offer: QA Engineer', 'text' => 'Your application has been approved and onboarding steps are prepared.'],
        ];

        return $this->render('front/modules/job_applications.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events', name: 'front_events')]
    public function events(Request $request, EntityManagerInterface $entityManager): Response
    {
        try {
            $entityManager->getConnection()->executeStatement('ALTER TABLE event_registration MODIFY candidate_id BIGINT DEFAULT NULL');
        } catch (\Throwable $t) {}

        $role = (string) $request->query->get('role', 'candidate');
        $session = $request->getSession();
        
        $candidateName = $session->get('candidate_name');
        $registeredIds = [];
        
        if ($candidateName) {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_name' => $candidateName]);
            foreach ($myRegs as $r) {
                if ($r->getEvent_id()) {
                    $registeredIds[] = $r->getEvent_id()->getId();
                }
            }
            $session->set('registered_event_ids', $registeredIds);
        }

        $events = $entityManager->getRepository(Recruitment_event::class)->findAll();

        $cards = [];
        foreach ($events as $event) {
            $cards[] = [
                'id' => $event->getId(),
                'meta' => $event->getEvent_date()->format('d M Y') . ' | ' . $event->getLocation(),
                'title' => $event->getTitle(),
                'text' => $event->getDescription(),
                'event_type' => $event->getEvent_type(),
                'location' => $event->getLocation(),
                'capacity' => $event->getCapacity(),
                'meet_link' => $event->getMeet_link(),
                'event_date_value' => $event->getEvent_date()->format('Y-m-d\TH:i'),
                'registered' => in_array($event->getId(), $registeredIds, true),
            ];
        }

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events/register/{id}', name: 'front_event_register', methods: ['POST'])]
    public function registerEvent(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $session = $request->getSession();
        $registeredIds = $session->get('registered_event_ids', []);
        
        $candidateName = $session->get('candidate_name');
        $candidateEmail = $session->get('candidate_email');
        if (!$candidateName) {
            $candidateName = 'Candidate ' . substr(md5($session->getId()), 0, 8);
            $candidateEmail = $candidateName . '@demo.local';
            $session->set('candidate_name', $candidateName);
            $session->set('candidate_email', $candidateEmail);
        }
        
        if (!in_array($id, $registeredIds, true)) {
            $registeredIds[] = $id;
            $session->set('registered_event_ids', $registeredIds);
            
            $registrationRepository = $entityManager->getRepository(Event_registration::class);
            
            $qb = $registrationRepository->createQueryBuilder('er')
                ->where('IDENTITY(er.event_id) = :eventId AND er.candidate_name = :candidateName')
                ->setParameter('eventId', $event->getId())
                ->setParameter('candidateName', $candidateName)
                ->getQuery();
            
            $existing = $qb->getOneOrNullResult();
            
            if (!$existing) {
                $registration = new Event_registration();
                $registration->setId((string) mt_rand(10000000, 99999999));
                $registration->setEvent_id($event);
                $registration->setCandidate_name($candidateName);
                $registration->setCandidate_email($candidateEmail);
                $registration->setRegistered_at(new \DateTime());
                $registration->setAttendance_status('registered');
                
                $entityManager->persist($registration);
                $entityManager->flush();
            }
            
            $message = sprintf('You have successfully registered for "%s".', $event->getTitle());
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'message' => $message]);
            }
            $this->addFlash('success', $message);
        } else {
            $message = sprintf('You are already registered for "%s".', $event->getTitle());
            if ($request->isXmlHttpRequest()) {
                return $this->json(['warning' => true, 'message' => $message]);
            }
            $this->addFlash('warning', $message);
        }

        return $this->redirectToRoute('front_events', ['role' => 'candidate']);
    }

    #[Route('/front/events/unregister/{id}', name: 'front_event_unregister', methods: ['POST'])]
    public function unregisterEvent(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $session = $request->getSession();
        $registeredIds = $session->get('registered_event_ids', []);
        $registeredIds = array_values(array_filter($registeredIds, function ($registeredId) use ($id) {
            return (int)$registeredId !== $id;
        }));
        $session->set('registered_event_ids', $registeredIds);

        $candidateName = $session->get('candidate_name');
        if ($candidateName) {
            $registration = $entityManager->getRepository(Event_registration::class)->findOneBy([
                'event_id' => $event,
                'candidate_name' => $candidateName
            ]);
            
            if ($registration) {
                $entityManager->remove($registration);
                $entityManager->flush();
            }
        }

        $this->addFlash('success', sprintf('You have cancelled registration for "%s".', $event->getTitle()));
        return $this->redirectToRoute('front_event_registrations', ['role' => 'candidate']);
    }

    #[Route('/front/events/registrations', name: 'front_event_registrations')]
    public function registrations(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $session = $request->getSession();
        
        $candidateName = $session->get('candidate_name');
        $registeredIds = [];
        
        if ($candidateName) {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_name' => $candidateName]);
            foreach ($myRegs as $r) {
                if ($r->getEvent_id()) {
                    $registeredIds[] = $r->getEvent_id()->getId();
                }
            }
            $session->set('registered_event_ids', $registeredIds);
        }

        $cards = [];
        if ($candidateName) {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_name' => $candidateName]);
            foreach ($myRegs as $reg) {
                $event = $reg->getEvent_id();
                if ($event) {
                    $cards[] = [
                        'id' => $event->getId(),
                        'meta' => $event->getEvent_date()->format('d M Y') . ' | ' . $event->getLocation(),
                        'title' => $event->getTitle(),
                        'text' => $event->getDescription(),
                        'event_type' => $event->getEvent_type(),
                        'location' => $event->getLocation(),
                        'capacity' => $event->getCapacity(),
                        'meet_link' => $event->getMeet_link(),
                        'event_date_value' => $event->getEvent_date()->format('Y-m-d\TH:i'),
                        'status' => $reg->getAttendance_status() ?? 'registered',
                    ];
                }
            }
            
            usort($cards, function ($a, $b) {
                return strcmp($a['event_date_value'], $b['event_date_value']);
            });
        }

        return $this->render('front/modules/event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }

    #[Route('/front/events/unregister-all', name: 'front_event_unregister_all', methods: ['POST'])]
    public function unregisterAllEvents(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $session->set('registered_event_ids', []);
        
        $candidateName = $session->get('candidate_name');
        if ($candidateName) {
            $registrations = $entityManager->getRepository(Event_registration::class)->findBy([
                'candidate_name' => $candidateName
            ]);
            
            foreach ($registrations as $reg) {
                $entityManager->remove($reg);
            }
            if (count($registrations) > 0) {
                $entityManager->flush();
            }
        }
        $this->addFlash('success', 'You have cancelled registration for all events.');
        return $this->redirectToRoute('front_event_registrations', ['role' => 'candidate']);
    }

    #[Route('/front/recruiter/event-registrations', name: 'recruiter_event_registrations')]
    public function recruiterEventRegistrations(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = (string) $request->query->get('role', 'recruiter');
        $events = $entityManager->getRepository(Recruitment_event::class)->findAll();

        $eventsData = [];
        foreach ($events as $event) {
            $registrations = $event->getEvent_registrations();
            
            $candidatesList = [];
            foreach ($registrations as $reg) {
                $candidatesList[] = [
                    'registration_id' => $reg->getId(),
                    'name' => $reg->getCandidate_name() ?? 'Unknown',
                    'email' => $reg->getCandidate_email() ?? 'N/A',
                    'registered_at' => $reg->getRegistered_at(),
                    'status' => $reg->getAttendance_status() ?? 'registered',
                ];
            }
            
            $eventsData[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'meta' => $event->getEvent_date()->format('d M Y') . ' | ' . $event->getLocation(),
                'date' => $event->getEvent_date(),
                'location' => $event->getLocation(),
                'capacity' => $event->getCapacity(),
                'event_type' => $event->getEvent_type(),
                'registrations' => $candidatesList,
                'registration_count' => count($candidatesList),
            ];
        }

        return $this->render('front/modules/recruiter_event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'events' => $eventsData,
        ]);
    }

    #[Route('/front/recruiter/event-registrations/{id}/status', name: 'recruiter_update_registration_status', methods: ['POST'])]
    public function updateRegistrationStatus(Request $request, string $id, EntityManagerInterface $entityManager): Response
    {
        $registration = $entityManager->getRepository(Event_registration::class)->find($id);
        if (!$registration) {
            throw $this->createNotFoundException('Registration not found');
        }

        $status = $request->request->get('status');
        if (in_array($status, ['confirmed', 'rejected'])) {
            $registration->setAttendance_status($status);
            $entityManager->flush();
            $this->addFlash('success', 'Registration status updated to ' . ucfirst($status) . '.');
        } else {
            $this->addFlash('warning', 'Invalid status provided.');
        }

        return $this->redirectToRoute('recruiter_event_registrations', ['role' => 'recruiter']);
    }

    #[Route('/front/interviews', name: 'front_interviews')]
    public function interviews(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => '15 Apr 2026 | 10:00 | Scheduled', 'title' => 'Interview: Frontend Engineer', 'text' => 'Prepare portfolio walkthrough and component design discussion.'],
            ['meta' => '18 Apr 2026 | 14:30 | Pending Feedback', 'title' => 'Interview: Symfony Backend Developer', 'text' => 'Technical round completed, feedback consolidation in progress.'],
            ['meta' => '22 Apr 2026 | 09:30 | Completed', 'title' => 'Interview: QA Engineer', 'text' => 'Process completed, final decision and follow-up underway.'],
        ];

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
    }
}
