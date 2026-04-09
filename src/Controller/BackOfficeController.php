<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Recruitment_event;
use App\Entity\Recruiter;

class BackOfficeController extends AbstractController
{
    #[Route('/admin', name: 'back_dashboard')]
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'authUser' => ['role' => 'admin'],
            'kpis' => [
                ['label' => 'Total Users', 'value' => '1,378', 'icon' => 'ti ti-users'],
                ['label' => 'Open Offers', 'value' => '32', 'icon' => 'ti ti-briefcase-2'],
                ['label' => 'Applications', 'value' => '3,580', 'icon' => 'ti ti-file-check'],
                ['label' => 'Interviews', 'value' => '482', 'icon' => 'ti ti-message-2'],
            ],
        ]);
    }

    #[Route('/admin/add-user', name: 'app_admin_add_user')]
    public function addUser(): Response
    {
        return $this->render('admin/add_user.html.twig', [
            'authUser' => ['role' => 'admin'],
        ]);
    }

    #[Route('/recruiter/create-event', name: 'recruiter_create_event', methods: ['GET', 'POST'])]
    public function createEvent(Request $request, EntityManagerInterface $entityManager): Response
    {
        $errors = [];
        
        if ($request->isMethod('POST')) {
            // Collect and validate input
            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));
            $eventType = trim($request->request->get('event_type', ''));
            $location = trim($request->request->get('location', ''));
            $eventDate = $request->request->get('event_date', '');
            $capacity = $request->request->get('capacity', '');
            $meetLink = trim($request->request->get('meet_link', ''));

            // Validation rules
            if (empty($title)) {
                $errors['title'] = 'Event title is required.';
            } elseif (strlen($title) < 3) {
                $errors['title'] = 'Event title must be at least 3 characters.';
            } elseif (strlen($title) > 255) {
                $errors['title'] = 'Event title cannot exceed 255 characters.';
            }

            if (empty($description)) {
                $errors['description'] = 'Description is required.';
            } elseif (strlen($description) < 10) {
                $errors['description'] = 'Description must be at least 10 characters.';
            }

            if (empty($eventType)) {
                $errors['event_type'] = 'Event type is required.';
            } elseif (!in_array($eventType, ['Workshop', 'Hiring Day', 'Webinar'])) {
                $errors['event_type'] = 'Invalid event type selected.';
            }

            if (empty($location)) {
                $errors['location'] = 'Location is required.';
            } elseif (strlen($location) < 2) {
                $errors['location'] = 'Location must be at least 2 characters.';
            }

            if (empty($eventDate)) {
                $errors['event_date'] = 'Event date is required.';
            } else {
                try {
                    $date = new \DateTime($eventDate);
                    $now = new \DateTime();
                    if ($date <= $now) {
                        $errors['event_date'] = 'Event date must be in the future.';
                    }
                } catch (\Exception $e) {
                    $errors['event_date'] = 'Invalid date format.';
                }
            }

            if (empty($capacity)) {
                $errors['capacity'] = 'Capacity is required.';
            } else {
                $capacityInt = (int)$capacity;
                if ($capacityInt < 1) {
                    $errors['capacity'] = 'Capacity must be at least 1.';
                } elseif ($capacityInt > 1000) {
                    $errors['capacity'] = 'Capacity cannot exceed 1000.';
                }
            }

            if (!empty($meetLink) && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
                $errors['meet_link'] = 'Please enter a valid URL.';
            }

            // If no errors, save the event
            if (empty($errors)) {
                $recruiter = $entityManager->getRepository(Recruiter::class)->findOneBy([]);
                if (!$recruiter) {
                    $this->addFlash('error', 'No recruiter account was found. Please create a recruiter record first.');
                    return $this->redirectToRoute('recruiter_create_event');
                }

                $event = new Recruitment_event();
                $event->setId((string) mt_rand(10000000, 99999999));
                $event->setRecruiter_id($recruiter);
                $event->setTitle($title);
                $event->setDescription($description);
                $event->setEvent_type($eventType);
                $event->setLocation($location);
                $event->setEvent_date(new \DateTime($eventDate));
                $event->setCapacity((int)$capacity);
                $event->setMeet_link($meetLink);
                $event->setCreated_at(new \DateTime());

                $entityManager->persist($event);
                $entityManager->flush();

                $this->addFlash('success', 'Event created successfully!');
                return $this->redirectToRoute('front_events');
            }
        }

        return $this->render('back/create_event.html.twig', [
            'authUser' => ['role' => 'recruiter'],
            'errors' => $errors,
        ]);
    }

    #[Route('/recruiter/delete-event/{id}', name: 'recruiter_delete_event', methods: ['POST'])]
    public function deleteEvent(int $id, EntityManagerInterface $entityManager): Response
    {
        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $entityManager->remove($event);
        $entityManager->flush();

        $this->addFlash('success', 'Event deleted successfully!');
        return $this->redirectToRoute('front_events');
    }

    #[Route('/recruiter/update-event/{id}', name: 'recruiter_update_event', methods: ['POST'])]
    public function updateEvent(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $errors = [];
        $title = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));
        $eventType = trim($request->request->get('event_type', ''));
        $location = trim($request->request->get('location', ''));
        $eventDate = $request->request->get('event_date', '');
        $capacity = $request->request->get('capacity', '');
        $meetLink = trim($request->request->get('meet_link', ''));

        if (empty($title)) {
            $errors['title'] = 'Event title is required.';
        } elseif (strlen($title) < 3) {
            $errors['title'] = 'Event title must be at least 3 characters.';
        } elseif (strlen($title) > 255) {
            $errors['title'] = 'Event title cannot exceed 255 characters.';
        }

        if (empty($description)) {
            $errors['description'] = 'Description is required.';
        } elseif (strlen($description) < 10) {
            $errors['description'] = 'Description must be at least 10 characters.';
        }

        if (empty($eventType)) {
            $errors['event_type'] = 'Event type is required.';
        } elseif (!in_array($eventType, ['Workshop', 'Hiring Day', 'Webinar'])) {
            $errors['event_type'] = 'Invalid event type selected.';
        }

        if (empty($location)) {
            $errors['location'] = 'Location is required.';
        } elseif (strlen($location) < 2) {
            $errors['location'] = 'Location must be at least 2 characters.';
        }

        if (empty($eventDate)) {
            $errors['event_date'] = 'Event date is required.';
        } else {
            try {
                $date = new \DateTime($eventDate);
                $now = new \DateTime();
                if ($date <= $now) {
                    $errors['event_date'] = 'Event date must be in the future.';
                }
            } catch (\Exception $e) {
                $errors['event_date'] = 'Invalid date format.';
            }
        }

        if (empty($capacity)) {
            $errors['capacity'] = 'Capacity is required.';
        } else {
            $capacityInt = (int)$capacity;
            if ($capacityInt < 1) {
                $errors['capacity'] = 'Capacity must be at least 1.';
            } elseif ($capacityInt > 1000) {
                $errors['capacity'] = 'Capacity cannot exceed 1000.';
            }
        }

        if (!empty($meetLink) && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
            $errors['meet_link'] = 'Please enter a valid URL.';
        }

        if (!empty($errors)) {
            $this->addFlash('warning', 'Event could not be updated. Please fix the errors.');
            return $this->redirectToRoute('front_events', ['role' => 'recruiter']);
        }

        $event->setTitle($title);
        $event->setDescription($description);
        $event->setEvent_type($eventType);
        $event->setLocation($location);
        $event->setEvent_date(new \DateTime($eventDate));
        $event->setCapacity((int)$capacity);
        $event->setMeet_link($meetLink);

        $entityManager->flush();

        $this->addFlash('success', 'Event updated successfully!');
        return $this->redirectToRoute('front_events');
    }
}
