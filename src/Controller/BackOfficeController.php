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
        if ($request->isMethod('POST')) {
            // Static recruiter ID for now
            $recruiterId = 1; // Assuming recruiter with ID 1 exists

            $recruiter = $entityManager->getRepository(Recruiter::class)->find($recruiterId);
            if (!$recruiter) {
                throw $this->createNotFoundException('Recruiter not found');
            }

            $event = new Recruitment_event();
            $event->setRecruiter_id($recruiter);
            $event->setTitle($request->request->get('title'));
            $event->setDescription($request->request->get('description'));
            $event->setEvent_type($request->request->get('event_type'));
            $event->setLocation($request->request->get('location'));
            $event->setEvent_date(new \DateTime($request->request->get('event_date')));
            $event->setCapacity((int)$request->request->get('capacity'));
            $event->setMeet_link($request->request->get('meet_link'));
            $event->setCreated_at(new \DateTime());

            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Event created successfully!');
            return $this->redirectToRoute('front_events');
        }

        return $this->render('back/create_event.html.twig', [
            'authUser' => ['role' => 'recruiter'],
        ]);
    }
}
