<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\ProfileType;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;

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
    public function events(Request $request): Response
    {
        $role = (string) $request->query->get('role', 'candidate');
        $cards = [
            ['meta' => '12 Apr 2026 | Tunis', 'title' => 'Tech Hiring Day', 'text' => 'Meet recruiters and discover active engineering opportunities.'],
            ['meta' => '20 Apr 2026 | Sousse', 'title' => 'Career Talk', 'text' => 'Panel discussion with hiring managers and senior developers.'],
            ['meta' => '28 Apr 2026 | Remote', 'title' => 'Virtual Assessment Workshop', 'text' => 'Online guidance session for interview and coding assessments.'],
        ];

        return $this->render('front/modules/events.html.twig', [
            'authUser' => ['role' => $role],
            'cards' => $cards,
        ]);
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


#[Route('/front/profile', name: 'front_profile')]
public function profile(
    Request $request, 
    UsersRepository $userRepo, 
    EntityManagerInterface $entityManager
): Response {
    // 1. Get the user_id from the session you set in LoginController
    $userId = $request->getSession()->get('user_id');

    // 2. Fetch the actual entity from the database
    $user = $userRepo->find($userId);

    if (!$user) {
        $this->addFlash('error', 'Please log in to access your profile.');
        return $this->redirectToRoute('app_login');
    }

    // 3. Pass the actual $user entity to the form
    // Symfony will call $user->getFirstName(), $user->getLastName(), etc.
    $form = $this->createForm(ProfileType::class, $user);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // 4. Save the changes back to the database
        $entityManager->flush();

        // Optional: Update the session 'user_name' if they changed their first name
        $request->getSession()->set('user_name', $user->getFirstName());

        $this->addFlash('success', 'Profile updated successfully!');
        return $this->redirectToRoute('front_profile');
    }

    return $this->render('front/profile.html.twig', [
        'form' => $form->createView(),
        'authUser' => ['role' => 'candidate'], 
    ]);
}

}
