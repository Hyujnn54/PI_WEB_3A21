<?php

namespace App\Controller;

<<<<<<< Updated upstream
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Recruitment_event;
use App\Entity\Event_registration;
=======
use App\Entity\Candidate;
use App\Entity\Candidate_skill;
use App\Entity\Event_registration;
use App\Entity\Event_review;
use App\Entity\Interview;
use App\Entity\Interview_feedback;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use App\Form\ProfileType;
use App\Repository\UsersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Throwable;
>>>>>>> Stashed changes

class FrontPortalController extends AbstractController
{
<<<<<<< Updated upstream
=======
    private const MAX_FUTURE_DAYS = 90;
    private const EDIT_LOCK_HOURS = 2;
    private const LOCATION_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,120}$/u';
    private const TEXTAREA_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{0,1000}$/u';
    private const REVIEW_COMMENT_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,1000}$/u';
    private const CONTRACT_TYPES = ['CDI', 'CDD', 'Internship', 'Freelance', 'Part-time', 'Remote Contract'];
    private const SKILL_LEVELS = ['beginner', 'intermediate', 'advanced'];
    private const JOB_STATUSES = ['open', 'paused', 'closed'];
    private const EVENT_URGENCY_WINDOW_HOURS = 72;

    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

>>>>>>> Stashed changes
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
    public function events(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        try {
            $entityManager->getConnection()->executeStatement('ALTER TABLE event_registration MODIFY candidate_id BIGINT DEFAULT NULL');
        } catch (\Throwable $t) {}

        $role = (string) $request->query->get('role', 'candidate');
        $session = $request->getSession();
        
        $candidateName = $session->get('candidate_name');
        $registeredIds = [];
<<<<<<< Updated upstream
        
        if ($candidateName) {
=======
        $candidateEmail = trim((string) $session->get('candidate_email', ''));

        $candidate = $this->resolveCurrentCandidate($request);
        $candidateName = trim((string) $session->get('candidate_name', ''));

        if ($candidate instanceof Candidate) {
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_id' => $candidate]);
            $candidateEmail = trim((string) $candidate->getEmail());
        } elseif ($candidateName !== '') {
>>>>>>> Stashed changes
            $myRegs = $entityManager->getRepository(Event_registration::class)->findBy(['candidate_name' => $candidateName]);
            foreach ($myRegs as $r) {
                if ($r->getEvent_id()) {
                    $registeredIds[] = $r->getEvent_id()->getId();
                }
            }
            $session->set('registered_event_ids', $registeredIds);
        }

<<<<<<< Updated upstream
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
=======
        $registeredTypePreferences = [];
        if ($role === 'candidate' && count($myRegs) > 0) {
            foreach ($myRegs as $registration) {
                if (!$registration instanceof Event_registration) {
                    continue;
                }

                $registeredEvent = $registration->getEvent_id();
                if (!$registeredEvent instanceof Recruitment_event) {
                    continue;
                }

                $normalizedType = strtolower(trim((string) $registeredEvent->getEvent_type()));
                if ($normalizedType !== '') {
                    $registeredTypePreferences[$normalizedType] = true;
                }
            }
        }

        if ($role === 'recruiter') {
            $recruiter = $this->resolveCurrentRecruiter($request);
            if (!$recruiter instanceof Recruiter) {
                $eventsPagination = $paginator->paginate([], 1, 6, [
                    'pageParameterName' => 'page',
                ]);
                $eventsToProcess = [];
            } else {
                $queryBuilder = $entityManager->getRepository(Recruitment_event::class)
                    ->createQueryBuilder('e')
                    ->andWhere('e.recruiter_id = :recruiter')
                    ->setParameter('recruiter', $recruiter)
                    ->orderBy('e.id', 'DESC');

                $eventsPagination = $paginator->paginate(
                    $queryBuilder,
                    max(1, $request->query->getInt('page', 1)),
                    6,
                    ['pageParameterName' => 'page']
                );
                $eventsToProcess = iterator_to_array($eventsPagination->getItems());
            }
        } else {
            $eventsToProcess = $entityManager->getRepository(Recruitment_event::class)
                ->findBy([], ['id' => 'DESC']);
        }

        $eventStatsByEvent = [];
        $statsRows = $entityManager->getConnection()->fetchAllAssociative(
            "SELECT event_id, COUNT(*) AS total_count, SUM(CASE WHEN LOWER(attendance_status) = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count FROM event_registration GROUP BY event_id"
        );

        foreach ($statsRows as $row) {
            $eventId = (string) ($row['event_id'] ?? '');
            if ($eventId === '') {
                continue;
            }

            $totalCount = (int) ($row['total_count'] ?? 0);
            $confirmedCount = (int) ($row['confirmed_count'] ?? 0);
            $eventStatsByEvent[$eventId] = [
                'total_count' => $totalCount,
                'confirmed_count' => $confirmedCount,
            ];
        }

        $eventIdsToProcess = array_values(array_filter(array_map(
            static fn (Recruitment_event $event): int => (int) $event->getId(),
            $eventsToProcess
        ), static fn (int $id): bool => $id > 0));

        $reviewStatsByEvent = [];
        if (count($eventIdsToProcess) > 0) {
            $reviewRows = $entityManager->createQuery(
                'SELECT IDENTITY(er.event_id) AS event_id, AVG(er.rating) AS average_rating, COUNT(er.id) AS review_count FROM App\\Entity\\Event_review er WHERE IDENTITY(er.event_id) IN (:eventIds) GROUP BY er.event_id'
            )
                ->setParameter('eventIds', $eventIdsToProcess)
                ->getArrayResult();

            foreach ($reviewRows as $reviewRow) {
                $reviewEventId = (string) ($reviewRow['event_id'] ?? '');
                if ($reviewEventId === '') {
                    continue;
                }

                $reviewStatsByEvent[$reviewEventId] = [
                    'average_rating' => round((float) ($reviewRow['average_rating'] ?? 0), 1),
                    'review_count' => (int) ($reviewRow['review_count'] ?? 0),
                ];
            }
        }

        $myReviewsByEvent = [];
        if ($candidate instanceof Candidate && count($eventIdsToProcess) > 0) {
            $candidateReviews = $entityManager->createQuery(
                'SELECT er FROM App\\Entity\\Event_review er WHERE er.candidate_id = :candidate AND IDENTITY(er.event_id) IN (:eventIds) ORDER BY er.created_at DESC'
            )
                ->setParameter('candidate', $candidate)
                ->setParameter('eventIds', $eventIdsToProcess)
                ->getResult();

            foreach ($candidateReviews as $candidateReview) {
                if (!$candidateReview instanceof Event_review) {
                    continue;
                }

                $reviewEvent = $candidateReview->getEvent_id();
                $reviewEventId = $reviewEvent instanceof Recruitment_event ? (string) $reviewEvent->getId() : '';
                if ($reviewEventId === '' || isset($myReviewsByEvent[$reviewEventId])) {
                    continue;
                }

                $myReviewsByEvent[$reviewEventId] = $candidateReview;
            }
        }

        $cards = [];
        $now = new \DateTimeImmutable();
        foreach ($eventsToProcess as $event) {
            $description = trim((string) $event->getDescription());
            $eventId = (string) $event->getId();
            $eventDate = $event->getEvent_date();
            $stats = $eventStatsByEvent[$eventId] ?? [
                'total_count' => 0,
                'confirmed_count' => 0,
            ];
            $capacity = max(0, (int) $event->getCapacity());
            $confirmedCount = (int) $stats['confirmed_count'];
            $popularityPercent = $capacity > 0 ? (int) round(($confirmedCount / $capacity) * 100) : 0;
            $isPopular = $capacity > 0 && $confirmedCount >= (int) ceil($capacity * 0.7);
            $reviewStats = $reviewStatsByEvent[$eventId] ?? [
                'average_rating' => 0.0,
                'review_count' => 0,
            ];
            $reviewCount = (int) ($reviewStats['review_count'] ?? 0);
            $averageRating = (float) ($reviewStats['average_rating'] ?? 0);
            $candidateReview = $myReviewsByEvent[$eventId] ?? null;
            $normalizedEventType = strtolower(trim((string) $event->getEvent_type()));

            $isRegistered = in_array($event->getId(), $registeredIds, true);
            $eventHasPassed = $eventDate <= $now;
            $canReview = $role === 'candidate' && $candidate instanceof Candidate && $isRegistered && $eventHasPassed;
            $isRecommended = $role === 'candidate'
                && !$isRegistered
                && !$eventHasPassed
                && $normalizedEventType !== ''
                && isset($registeredTypePreferences[$normalizedEventType]);
            $reviewLockedReason = '';
            if ($role === 'candidate' && !$canReview) {
                if (!$isRegistered) {
                    $reviewLockedReason = 'Register to this event to submit a review.';
                } elseif (!$eventHasPassed) {
                    $reviewLockedReason = 'Reviews open after the event date has passed.';
                } else {
                    $reviewLockedReason = 'Only logged-in candidate accounts can submit reviews.';
                }
            }

            $startUtc = (clone $eventDate)->setTimezone(new \DateTimeZone('UTC'));
            $endUtc = (clone $eventDate)->modify('+2 hours')->setTimezone(new \DateTimeZone('UTC'));
            $calendarDetails = trim((string) $event->getDescription());
            $calendarUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                . '&text=' . rawurlencode((string) $event->getTitle())
                . '&dates=' . rawurlencode($startUtc->format('Ymd\THis\Z') . '/' . $endUtc->format('Ymd\THis\Z'))
                . '&details=' . rawurlencode($calendarDetails)
                . '&location=' . rawurlencode((string) $event->getLocation());

            if ($candidateEmail !== '') {
                $calendarUrl .= '&authuser=' . rawurlencode($candidateEmail);
            }

            $cards[] = [
                'id' => $event->getId(),
                'meta' => sprintf('%s | %s', $eventDate->format('d M Y'), (string) $event->getLocation()),
                'title' => (string) $event->getTitle(),
                'text' => $description === '' ? 'No event description available yet.' : substr($description, 0, 190),
                'event_type' => (string) $event->getEvent_type(),
                'location' => (string) $event->getLocation(),
                'capacity' => $capacity,
                'meet_link' => (string) $event->getMeet_link(),
                'event_date_value' => $eventDate->format('Y-m-d\TH:i'),
                'registered' => $isRegistered,
                'confirmed_count' => $confirmedCount,
                'total_registrations' => (int) $stats['total_count'],
                'popularity_percent' => $popularityPercent,
                'is_popular' => $isPopular,
                'is_recommended' => $isRecommended,
                'recommended_reason' => $isRecommended
                    ? sprintf('Recommended because you previously registered for %s events.', (string) $event->getEvent_type())
                    : '',
                'google_calendar_url' => $calendarUrl,
                'average_rating' => $averageRating,
                'review_count' => $reviewCount,
                'can_review' => $canReview,
                'review_locked_reason' => $reviewLockedReason,
                'my_review' => $candidateReview instanceof Event_review
                    ? [
                        'rating' => (int) $candidateReview->getRating(),
                        'comment' => (string) $candidateReview->getComment(),
                        'created_at' => $candidateReview->getCreated_at()->format('Y-m-d H:i'),
                    ]
                    : null,
            ];
        }

        if ($role === 'candidate' && count($registeredTypePreferences) > 0) {
            usort($cards, static function (array $leftCard, array $rightCard): int {
                $leftRecommended = !empty($leftCard['is_recommended']);
                $rightRecommended = !empty($rightCard['is_recommended']);

                if ($leftRecommended !== $rightRecommended) {
                    return $leftRecommended ? -1 : 1;
                }

                if ($leftRecommended && $rightRecommended) {
                    $leftRating = (float) ($leftCard['average_rating'] ?? 0);
                    $rightRating = (float) ($rightCard['average_rating'] ?? 0);
                    if ($leftRating !== $rightRating) {
                        return $rightRating <=> $leftRating;
                    }

                    $leftDate = (string) ($leftCard['event_date_value'] ?? '');
                    $rightDate = (string) ($rightCard['event_date_value'] ?? '');
                    if ($leftDate !== $rightDate) {
                        return strcmp($leftDate, $rightDate);
                    }

                    $leftPopularity = (int) ($leftCard['popularity_percent'] ?? 0);
                    $rightPopularity = (int) ($rightCard['popularity_percent'] ?? 0);
                    if ($leftPopularity !== $rightPopularity) {
                        return $rightPopularity <=> $leftPopularity;
                    }
                }

                $leftId = (int) ($leftCard['id'] ?? 0);
                $rightId = (int) ($rightCard['id'] ?? 0);

                return $rightId <=> $leftId;
            });
        }

        $eventsPagination = $paginator->paginate(
            $cards,
            max(1, $request->query->getInt('page', 1)),
            6,
            ['pageParameterName' => 'page']
        );

        $cards = iterator_to_array($eventsPagination->getItems());
>>>>>>> Stashed changes

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
            'eventsPagination' => $eventsPagination,
        ]);
    }

    #[Route('/front/events/{id}/review', name: 'front_event_review_submit', methods: ['POST'])]
    public function submitEventReview(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        $role = $this->resolveSessionRole($request);
        $page = max(1, $request->request->getInt('page', 1));

        if ($role !== 'candidate') {
            $this->addFlash('warning', 'Only candidates can submit event reviews.');
            return $this->redirectToRoute('front_events', ['role' => $role, 'page' => $page]);
        }

        $candidate = $this->resolveCurrentCandidate($request);
        if (!$candidate instanceof Candidate) {
            $this->addFlash('warning', 'Please log in with your candidate account to submit a review.');
            return $this->redirectToRoute('app_login');
        }

        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event instanceof Recruitment_event) {
            throw $this->createNotFoundException('Event not found.');
        }

        $registration = $entityManager->getRepository(Event_registration::class)->findOneBy([
            'event_id' => $event,
            'candidate_id' => $candidate,
        ]);

        if (!$registration instanceof Event_registration) {
            $this->addFlash('warning', 'You can only review events you registered for.');
            return $this->redirectToRoute('front_events', ['role' => 'candidate', 'page' => $page]);
        }

        $eventDate = $event->getEvent_date();
        if (!$eventDate instanceof \DateTimeInterface || $eventDate > new \DateTimeImmutable()) {
            $this->addFlash('warning', 'Reviews can be submitted only after the event has taken place.');
            return $this->redirectToRoute('front_events', ['role' => 'candidate', 'page' => $page]);
        }

        $rating = (int) $request->request->get('rating', 0);
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('warning', 'Rating must be between 1 and 5.');
            return $this->redirectToRoute('front_events', ['role' => 'candidate', 'page' => $page]);
        }

        $comment = trim((string) $request->request->get('comment', ''));
        $commentValidation = $this->validateReviewComment($comment);
        if (!$commentValidation['ok']) {
            $this->addFlash('warning', (string) $commentValidation['error']);
            return $this->redirectToRoute('front_events', ['role' => 'candidate', 'page' => $page]);
        }

        $reviewRepository = $entityManager->getRepository(Event_review::class);
        $review = $reviewRepository->findOneBy([
            'event_id' => $event,
            'candidate_id' => $candidate,
        ]);

        if (!$review instanceof Event_review) {
            $review = new Event_review();
            $review->setId($this->nextNumericId(Event_review::class));
            $review->setEvent_id($event);
            $review->setCandidate_id($candidate);
            $entityManager->persist($review);
        }

        $review->setRating($rating);
        $review->setComment((string) $commentValidation['value']);
        $review->setCreated_at(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Your event review has been saved.');
        return $this->redirectToRoute('front_events', ['role' => 'candidate', 'page' => $page]);
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
    public function recruiterEventRegistrations(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
<<<<<<< Updated upstream
        $role = (string) $request->query->get('role', 'recruiter');
        $events = $entityManager->getRepository(Recruitment_event::class)->findAll();
=======
        $role = $this->resolveSessionRole($request);
        if ($role !== 'recruiter') {
            $this->addFlash('warning', 'Only recruiters can view event registrations.');
            return $this->redirectToRoute('front_events');
        }

        $recruiter = $this->resolveCurrentRecruiter($request);
        if (!$recruiter instanceof Recruiter) {
            $eventsPagination = $paginator->paginate([], 1, 6, [
                'pageParameterName' => 'page',
            ]);
        } else {
            $queryBuilder = $entityManager->getRepository(Recruitment_event::class)
                ->createQueryBuilder('e')
                ->andWhere('e.recruiter_id = :recruiter')
                ->setParameter('recruiter', $recruiter)
                ->orderBy('e.id', 'DESC');

            $eventsPagination = $paginator->paginate(
                $queryBuilder,
                max(1, $request->query->getInt('page', 1)),
                6,
                ['pageParameterName' => 'page']
            );
        }
>>>>>>> Stashed changes

        $eventsData = [];
        $urgentNotifications = [];
        $now = new \DateTimeImmutable();
        foreach ($eventsPagination as $event) {
            $registrations = $event->getEvent_registrations();
            
            $candidatesList = [];
<<<<<<< Updated upstream
            foreach ($registrations as $reg) {
=======
            $pendingActionsCount = 0;
            foreach ($registrations as $registration) {
                $candidateEntity = $registration->getCandidate_id();
                $candidateFullName = '';
                $candidateEmail = '';

                if ($candidateEntity instanceof Candidate) {
                    $candidateFullName = trim(((string) $candidateEntity->getFirstName()) . ' ' . ((string) $candidateEntity->getLastName()));
                    if ($candidateFullName === '') {
                        $candidateFullName = trim((string) $candidateEntity->getFirstName());
                    }
                    $candidateEmail = (string) $candidateEntity->getEmail();
                }

                if ($candidateFullName === '') {
                    $candidateFullName = (string) ($registration->getCandidate_name() ?? 'Unknown');
                }
                if ($candidateEmail === '') {
                    $candidateEmail = (string) ($registration->getCandidate_email() ?? 'N/A');
                }

                $status = strtolower(trim((string) ($registration->getAttendance_status() ?? 'registered')));
                if (!in_array($status, ['confirmed', 'rejected'], true)) {
                    $pendingActionsCount++;
                }

>>>>>>> Stashed changes
                $candidatesList[] = [
                    'registration_id' => $reg->getId(),
                    'name' => $reg->getCandidate_name() ?? 'Unknown',
                    'email' => $reg->getCandidate_email() ?? 'N/A',
                    'registered_at' => $reg->getRegistered_at(),
                    'status' => $reg->getAttendance_status() ?? 'registered',
                ];
            }
<<<<<<< Updated upstream
            
=======

            $eventDate = $event->getEvent_date();
            $secondsUntilEvent = $eventDate instanceof \DateTimeInterface ? ($eventDate->getTimestamp() - $now->getTimestamp()) : -1;
            $hoursUntilEvent = $secondsUntilEvent > 0 ? (int) floor($secondsUntilEvent / 3600) : 0;
            $isDueSoon = $secondsUntilEvent > 0 && $secondsUntilEvent <= (self::EVENT_URGENCY_WINDOW_HOURS * 3600);
            $isUrgent = $isDueSoon && $pendingActionsCount > 0;

            if ($isUrgent) {
                $urgentNotifications[] = [
                    'event_id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'pending_count' => $pendingActionsCount,
                    'hours_left' => $hoursUntilEvent,
                    'event_date' => $eventDate,
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
                'pending_actions_count' => $pendingActionsCount,
                'is_urgent' => $isUrgent,
                'hours_until_event' => $hoursUntilEvent,
            ];
        }

        usort($urgentNotifications, static function (array $a, array $b): int {
            return ((int) $a['hours_left']) <=> ((int) $b['hours_left']);
        });

        return $this->render('front/modules/recruiter_event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'events' => $eventsData,
            'urgentNotifications' => $urgentNotifications,
            'eventsPagination' => $eventsPagination,
        ]);
    }

    #[Route('/front/admin/event-registrations', name: 'admin_event_registrations')]
    public function adminEventRegistrations(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        $role = $this->resolveSessionRole($request);
        if ($role !== 'admin') {
            $this->addFlash('warning', 'Only admins can view this registrations page.');
            return $this->redirectToRoute('front_events');
        }

        $queryBuilder = $entityManager->getRepository(Recruitment_event::class)
            ->createQueryBuilder('e')
            ->orderBy('e.id', 'DESC');

        $eventsPagination = $paginator->paginate(
            $queryBuilder,
            max(1, $request->query->getInt('page', 1)),
            6,
            ['pageParameterName' => 'page']
        );

        $eventsData = [];
        foreach ($eventsPagination as $event) {
            $registrations = $event->getEvent_registrations();

            $candidatesList = [];
            foreach ($registrations as $registration) {
                $candidateEntity = $registration->getCandidate_id();
                $candidateFullName = '';
                $candidateEmail = '';

                if ($candidateEntity instanceof Candidate) {
                    $candidateFullName = trim(((string) $candidateEntity->getFirstName()) . ' ' . ((string) $candidateEntity->getLastName()));
                    if ($candidateFullName === '') {
                        $candidateFullName = trim((string) $candidateEntity->getFirstName());
                    }
                    $candidateEmail = (string) $candidateEntity->getEmail();
                }

                if ($candidateFullName === '') {
                    $candidateFullName = (string) ($registration->getCandidate_name() ?? 'Unknown');
                }
                if ($candidateEmail === '') {
                    $candidateEmail = (string) ($registration->getCandidate_email() ?? 'N/A');
                }

                $candidatesList[] = [
                    'registration_id' => $registration->getId(),
                    'name' => $candidateFullName,
                    'email' => $candidateEmail,
                    'registered_at' => $registration->getRegistered_at(),
                    'status' => $registration->getAttendance_status() ?? 'registered',
                ];
            }

>>>>>>> Stashed changes
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
                'pending_actions_count' => 0,
                'is_urgent' => false,
                'hours_until_event' => 0,
            ];
        }

        return $this->render('front/modules/recruiter_event_registrations.html.twig', [
            'authUser' => ['role' => $role],
            'events' => $eventsData,
            'urgentNotifications' => [],
            'eventsPagination' => $eventsPagination,
        ]);
    }

    #[Route('/front/recruiter/event-registrations/{id}/status', name: 'recruiter_update_registration_status', methods: ['POST'])]
    public function updateRegistrationStatus(Request $request, string $id, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $registration = $entityManager->getRepository(Event_registration::class)->find($id);
        if (!$registration) {
            throw $this->createNotFoundException('Registration not found');
        }

<<<<<<< Updated upstream
        $status = $request->request->get('status');
        if (in_array($status, ['confirmed', 'rejected'])) {
=======
        $event = $registration->getEvent_id();
        $recruiter = $this->resolveCurrentRecruiter($request);
        if (!$event || !$recruiter instanceof Recruiter || $event->getRecruiter_id()->getId() !== $recruiter->getId()) {
            $this->addFlash('warning', 'You can only update registrations for your own events.');
            return $this->redirectToRoute('recruiter_event_registrations', ['role' => 'recruiter']);
        }

        $status = (string) $request->request->get('status');
        if (in_array($status, ['confirmed', 'rejected'], true)) {
            $previousStatus = strtolower(trim((string) $registration->getAttendance_status()));
>>>>>>> Stashed changes
            $registration->setAttendance_status($status);
            $entityManager->flush();

            if ($previousStatus !== $status) {
                $recipientEmail = trim((string) ($registration->getCandidate_email() ?? ''));
                $candidate = $registration->getCandidate_id();
                if ($recipientEmail === '' && $candidate instanceof Candidate) {
                    $recipientEmail = trim((string) $candidate->getEmail());
                }

                if ($recipientEmail !== '' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                    $candidateName = trim((string) ($registration->getCandidate_name() ?? ''));
                    if ($candidateName === '' && $candidate instanceof Candidate) {
                        $candidateName = trim((string) $candidate->getFirstName() . ' ' . (string) $candidate->getLastName());
                    }

                    $eventType = (string) $event->getEvent_type();
                    $eventTitle = (string) $event->getTitle();
                    $eventDate = $event->getEvent_date() instanceof \DateTimeInterface ? $event->getEvent_date()->format('d M Y H:i') : 'N/A';
                    $subjectAction = $status === 'confirmed' ? 'confirmed' : 'rejected';
                    $subject = sprintf('Your %s registration for %s has been %s', strtolower($eventType), $eventTitle, $subjectAction);

                    $eventTypeMessage = match ($eventType) {
                        'Workshop' => $status === 'confirmed'
                            ? 'Your workshop seat is confirmed. Please arrive a few minutes early and bring anything required for the session.'
                            : 'We had to decline your workshop registration for this session.',
                        'Hiring Day' => $status === 'confirmed'
                            ? 'Your hiring day registration is confirmed. Please keep your documents ready for the event.'
                            : 'We could not confirm your hiring day registration for this event.',
                        'Webinar' => $status === 'confirmed'
                            ? 'Your webinar registration is confirmed. Join details will be available in the event information.'
                            : 'We could not confirm your webinar registration for this session.',
                        default => $status === 'confirmed'
                            ? 'Your event registration is confirmed.'
                            : 'Your event registration was not approved.',
                    };

                    $closingLine = $status === 'confirmed'
                        ? 'We look forward to seeing you there.'
                        : 'Thank you for your interest in our events.';

                    $meetingLink = $status === 'confirmed' && $eventType === 'Webinar' ? trim((string) $event->getMeet_link()) : '';
                    $qrCodeUrl = '';
                    if ($status === 'confirmed' && in_array($eventType, ['Workshop', 'Hiring Day'], true)) {
                        $brandName = 'Talent Bridge';
                        $qrData = implode(' | ', array_filter([
                            $brandName,
                            'Registration ID: ' . (string) $registration->getId(),
                            'Event ID: ' . (string) $event->getId(),
                            'Event: ' . $eventTitle,
                            'Type: ' . $eventType,
                            'Date: ' . $eventDate,
                            'Candidate: ' . ($candidateName !== '' ? $candidateName : $recipientEmail),
                            'Email: ' . $recipientEmail,
                        ]));
                        $qrCodeUrl = sprintf(
                            'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=%s',
                            rawurlencode($qrData)
                        );
                    }

                    $message = (new TemplatedEmail())
                        ->from('rayanbenamor207@gmail.com')
                        ->to($recipientEmail)
                        ->subject($subject)
                        ->htmlTemplate('emails/recruitment_event_status.html.twig')
                        ->context([
                            'candidateName' => $candidateName,
                            'eventTitle' => $eventTitle,
                            'eventType' => $eventType,
                            'eventDate' => $eventDate,
                            'status' => $status,
                            'statusLabel' => ucfirst($status),
                            'eventTypeMessage' => $eventTypeMessage,
                            'closingLine' => $closingLine,
                            'meetingLink' => $meetingLink,
                            'qrCodeUrl' => $qrCodeUrl,
                            'brandName' => 'Talent Bridge',
                            'brandEmail' => 'rayanbenamor207@gmail.com',
                            'supportEmail' => 'rayanbenamor207@gmail.com',
                            'brandLogoUrl' => $this->generateUrl('front_home', ['role' => 'candidate'], UrlGeneratorInterface::ABSOLUTE_URL),
                            'eventsUrl' => $this->generateUrl('front_events', ['role' => 'candidate'], UrlGeneratorInterface::ABSOLUTE_URL),
                        ]);

                    try {
                        $mailer->send($message);
                    } catch (TransportExceptionInterface) {
                        $this->addFlash('warning', 'Status updated, but the notification email could not be sent.');
                    }
                } else {
                    $this->addFlash('warning', 'Status updated, but no valid candidate email was available to notify.');
                }
            }

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
<<<<<<< Updated upstream
=======

    public function setApplicationStatus(string $applicationId, string $status, Request $request): RedirectResponse
    {
        $role = $this->resolveSessionRole($request);
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
        $role = $this->resolveSessionRole($request);
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

    #[Route('/front/interviews/create/{applicationId}', name: 'front_interview_create', methods: ['GET', 'POST'])]
    public function createInterview(string $applicationId, Request $request): Response
    {
        $role = $this->resolveSessionRole($request);
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
        $role = $this->resolveSessionRole($request);
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
                $interview->setScheduled_at($validation['scheduledAt']);
                $interview->setDuration_minutes($validation['duration']);
                $interview->setMode($validation['mode']);
                $interview->setMeeting_link($validation['meetingLink']);
                $interview->setLocation($validation['location']);
                $interview->setNotes($validation['notes']);
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
            'formData' => $formData,
        ]);
    }

    #[Route('/front/interviews/{id}/delete', name: 'front_interview_delete', methods: ['POST'])]
    public function deleteInterview(string $id, Request $request): RedirectResponse
    {
        $role = $this->resolveSessionRole($request);
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
        $role = $this->resolveSessionRole($request);
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

    #[Route('/front/profile', name: 'front_profile')]
    public function profile(Request $request, UsersRepository $userRepo, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $role = $this->resolveSessionRole($request);
        $userId = $request->getSession()->get('user_id');
        $user = $userRepo->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Please log in to access your profile.');
            return $this->redirectToRoute('app_login');
        }

        $candidateSkills = [];
        if ($role === 'candidate') {
            $candidate = $this->resolveCurrentCandidate($request);

            if ($request->isMethod('POST') && $request->request->get('profile_action') === 'skill_add') {
                if (!$candidate instanceof Candidate) {
                    $this->addFlash('warning', 'Only candidates can manage skills.');
                    return $this->redirectToRoute('front_profile');
                }

                $skillName = trim((string) $request->request->get('skill_name', ''));
                $skillLevel = trim((string) $request->request->get('skill_level', ''));
                $allowedLevels = ['beginner', 'intermediate', 'advanced'];

                if ($skillName === '') {
                    $this->addFlash('warning', 'Skill name is required.');
                } elseif (mb_strlen($skillName) > 100) {
                    $this->addFlash('warning', 'Skill name must not exceed 100 characters.');
                } elseif (!in_array($skillLevel, $allowedLevels, true)) {
                    $this->addFlash('warning', 'Please select a valid skill level.');
                } else {
                    $skill = new Candidate_skill();
                    $skill->setSkillName($skillName);
                    $skill->setLevel($skillLevel);
                    $skill->setCandidate($candidate);

                    $entityManager->persist($skill);
                    $entityManager->flush();
                    $this->addFlash('success', 'Skill added successfully.');
                }

                return $this->redirectToRoute('front_profile');
            }

            if ($request->isMethod('POST') && $request->request->get('profile_action') === 'skill_delete') {
                if (!$candidate instanceof Candidate) {
                    $this->addFlash('warning', 'Only candidates can manage skills.');
                    return $this->redirectToRoute('front_profile');
                }

                $skillId = (int) $request->request->get('skill_id', 0);
                $skill = $entityManager->getRepository(Candidate_skill::class)->find($skillId);

                if (!$skill instanceof Candidate_skill || !$skill->getCandidate() instanceof Candidate || (string) $skill->getCandidate()->getId() !== (string) $candidate->getId()) {
                    $this->addFlash('warning', 'Skill not found or not allowed.');
                } else {
                    $entityManager->remove($skill);
                    $entityManager->flush();
                    $this->addFlash('success', 'Skill removed successfully.');
                }

                return $this->redirectToRoute('front_profile');
            }

            if ($request->isMethod('POST') && $request->request->get('profile_action') === 'skill_update') {
                if (!$candidate instanceof Candidate) {
                    $this->addFlash('warning', 'Only candidates can manage skills.');
                    return $this->redirectToRoute('front_profile');
                }

                $skillId = (int) $request->request->get('skill_id', 0);
                $skillName = trim((string) $request->request->get('skill_name', ''));
                $skillLevel = trim((string) $request->request->get('skill_level', ''));
                $allowedLevels = ['beginner', 'intermediate', 'advanced'];

                $skill = $entityManager->getRepository(Candidate_skill::class)->find($skillId);
                if (!$skill instanceof Candidate_skill || !$skill->getCandidate() instanceof Candidate || (string) $skill->getCandidate()->getId() !== (string) $candidate->getId()) {
                    $this->addFlash('warning', 'Skill not found or not allowed.');
                    return $this->redirectToRoute('front_profile');
                }

                if ($skillName === '') {
                    $this->addFlash('warning', 'Skill name is required.');
                    return $this->redirectToRoute('front_profile');
                }

                if (mb_strlen($skillName) > 100) {
                    $this->addFlash('warning', 'Skill name must not exceed 100 characters.');
                    return $this->redirectToRoute('front_profile');
                }

                if (!in_array($skillLevel, $allowedLevels, true)) {
                    $this->addFlash('warning', 'Please select a valid skill level.');
                    return $this->redirectToRoute('front_profile');
                }

                $skill->setSkillName($skillName);
                $skill->setLevel($skillLevel);
                $entityManager->flush();

                $this->addFlash('success', 'Skill updated successfully.');
                return $this->redirectToRoute('front_profile');
            }

            if ($candidate instanceof Candidate) {
                $candidateSkills = $entityManager->getRepository(Candidate_skill::class)->findBy([
                    'candidate' => $candidate,
                ], ['id' => 'DESC']);
            }
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $entityManager->flush();
            $request->getSession()->set('user_name', $user->getFirstName());

            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('front_profile');
        }

        return $this->render('front/profile.html.twig', [
            'form' => $form->createView(),
            'authUser' => ['role' => $role],
            'candidateSkills' => $candidateSkills,
        ]);
    }

    private function resolveCurrentUserId(Request $request): string
    {
        return (string) $request->getSession()->get('user_id', '');
    }

    private function resolveSessionRole(Request $request): string
    {
        $roles = (array) $request->getSession()->get('user_roles', []);
        if (in_array('ROLE_RECRUITER', $roles, true)) {
            return 'recruiter';
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'admin';
        }

        return 'candidate';
    }

    private function resolveCurrentCandidate(Request $request): ?Candidate
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === '') {
            return null;
        }

        $candidate = $this->doctrine->getRepository(Candidate::class)->find($userId);
        return $candidate instanceof Candidate ? $candidate : null;
    }

    private function resolveCurrentRecruiter(Request $request): ?Recruiter
    {
        $recruiterId = $this->resolveCurrentRecruiterId($request);
        if ($recruiterId === '') {
            return null;
        }

        $recruiter = $this->doctrine->getRepository(Recruiter::class)->find($recruiterId);
        return $recruiter instanceof Recruiter ? $recruiter : null;
    }

    private function resolveCurrentRecruiterId(Request $request): string
    {
        $userId = $this->resolveCurrentUserId($request);
        if ($userId === '') {
            return '';
        }

        $recruiterById = $this->doctrine->getRepository(Recruiter::class)->find($userId);
        if ($recruiterById instanceof Recruiter) {
            return (string) $recruiterById->getId();
        }

        try {
            $legacyRecruiterId = $this->doctrine->getManager()->getConnection()->fetchOne(
                'SELECT id FROM recruiter WHERE user_id = :user_id LIMIT 1',
                ['user_id' => $userId]
            );
            if ($legacyRecruiterId !== false && $legacyRecruiterId !== null && (string) $legacyRecruiterId !== '') {
                return (string) $legacyRecruiterId;
            }
        } catch (\Throwable) {
            // Keep fallback behavior when legacy column is absent.
        }

        return $userId;
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

    private function normalizeInterviewMode(?string $mode, ?string $location = null, ?string $meetingLink = null): string
    {
        $value = strtolower(trim((string) $mode));
        if (in_array($value, ['onsite', 'on_site', 'on-site', 'on site', 'in_person', 'in-person', 'in person'], true)) {
            return 'onsite';
        }

        if (in_array($value, ['online', 'on_line', 'on-line', 'on line'], true)) {
            return 'online';
        }

        $normalizedLocation = trim((string) $location);
        $normalizedMeetingLink = trim((string) $meetingLink);
        if ($normalizedLocation !== '' && $normalizedMeetingLink === '') {
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

    private function normalizeSkills(array $rawSkills): array
    {
        $skills = [];

        foreach ($rawSkills as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            $level = trim((string) ($entry['level'] ?? ''));
            if ($name === '' || !in_array($level, self::SKILL_LEVELS, true)) {
                continue;
            }

            $skills[] = ['name' => $name, 'level' => $level];
        }

        return $skills;
    }

    private function buildOfferStats(array $offers): array
    {
        $totalPublished = count($offers);
        $totalClosed = 0;
        $totalOpen = 0;
        $cityStats = [];
        $contractStats = [];

        foreach ($offers as $offer) {
            $city = trim((string) ($offer['location'] ?? 'Unknown'));
            if ($city === '') {
                $city = 'Unknown';
            }

            $contractType = trim((string) ($offer['contract_type'] ?? 'Unknown'));
            if ($contractType === '') {
                $contractType = 'Unknown';
            }

            $status = strtolower(trim((string) ($offer['status'] ?? 'open')));
            $isClosed = $status === 'closed';
            $isOpen = $status === 'open';

            if ($isClosed) {
                $totalClosed += 1;
            }
            if ($isOpen) {
                $totalOpen += 1;
            }

            if (!isset($cityStats[$city])) {
                $cityStats[$city] = ['city' => $city, 'total' => 0, 'open' => 0, 'closed' => 0];
            }
            $cityStats[$city]['total'] += 1;
            if ($isOpen) {
                $cityStats[$city]['open'] += 1;
            }
            if ($isClosed) {
                $cityStats[$city]['closed'] += 1;
            }

            if (!isset($contractStats[$contractType])) {
                $contractStats[$contractType] = ['contract_type' => $contractType, 'total' => 0, 'open' => 0, 'closed' => 0];
            }
            $contractStats[$contractType]['total'] += 1;
            if ($isOpen) {
                $contractStats[$contractType]['open'] += 1;
            }
            if ($isClosed) {
                $contractStats[$contractType]['closed'] += 1;
            }
        }

        $closedPercentage = $totalPublished > 0 ? round(($totalClosed / $totalPublished) * 100, 2) : 0.0;
        $openPercentage = $totalPublished > 0 ? round(($totalOpen / $totalPublished) * 100, 2) : 0.0;

        $cityStatsList = array_values($cityStats);
        foreach ($cityStatsList as &$row) {
            $row['open_rate'] = $row['total'] > 0 ? round(($row['open'] / $row['total']) * 100, 2) : 0.0;
            $row['closed_rate'] = $row['total'] > 0 ? round(($row['closed'] / $row['total']) * 100, 2) : 0.0;
        }

        $contractStatsList = array_values($contractStats);
        foreach ($contractStatsList as &$row) {
            $row['percentage'] = $totalPublished > 0 ? round(($row['total'] / $totalPublished) * 100, 2) : 0.0;
        }

        usort($cityStatsList, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        usort($contractStatsList, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'total_published' => $totalPublished,
            'total_closed' => $totalClosed,
            'total_open' => $totalOpen,
            'closed_percentage' => $closedPercentage,
            'open_percentage' => $openPercentage,
            'city_stats' => $cityStatsList,
            'contract_stats' => $contractStatsList,
        ];
    }
>>>>>>> Stashed changes
}
