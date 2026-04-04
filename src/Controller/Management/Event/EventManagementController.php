<?php

namespace App\Controller\Management\Event;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventManagementController extends AbstractController
{
    #[Route('/admin/events', name: 'management_events')]
    public function index(): Response
    {
        return $this->render('management/event/index.html.twig', [
            'module' => 'Event Management',
            'description' => 'Manage recruitment events, registrations, and reviews.',
        ]);
    }
}
