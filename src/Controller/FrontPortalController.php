<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontPortalController extends AbstractController
{
    #[Route('/front/job-offers', name: 'front_job_offers')]
    public function jobOffers(Request $request): Response
    {
        $guard = $this->guardFront($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->render('front/modules/job_offers.html.twig', [
            'authUser' => $guard,
        ]);
    }

    #[Route('/front/job-applications', name: 'front_job_applications')]
    public function jobApplications(Request $request): Response
    {
        $guard = $this->guardFront($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->render('front/modules/job_applications.html.twig', [
            'authUser' => $guard,
        ]);
    }

    #[Route('/front/events', name: 'front_events')]
    public function events(Request $request): Response
    {
        $guard = $this->guardFront($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->render('front/modules/events.html.twig', [
            'authUser' => $guard,
        ]);
    }

    #[Route('/front/interviews', name: 'front_interviews')]
    public function interviews(Request $request): Response
    {
        $guard = $this->guardFront($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->render('front/modules/interviews.html.twig', [
            'authUser' => $guard,
        ]);
    }

    private function guardFront(Request $request): array|RedirectResponse
    {
        $authUser = $request->getSession()->get('auth_user');

        if (!$authUser) {
            return $this->redirectToRoute('app_login');
        }

        if (($authUser['role'] ?? '') === 'admin') {
            return $this->redirectToRoute('back_dashboard');
        }

        return $authUser;
    }
}
