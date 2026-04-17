<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/', name: 'app_entry')]
    public function entry(Request $request, UsersRepository $userRepo): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');
        $roles = $session->get('user_roles', []);

        // DEV AUTO-LOGIN BYPASS:
        // Original login gate (kept as comment as requested):
        // if (!$userId) {
        //     return $this->redirectToRoute('app_login');
        // }
        if (!$userId) {
            // Prefer recruiter account for dev bypass so recruiter-only pages (Create Offer) work immediately.
            $devUser = $userRepo->findOneValidByEmail('recruiter@gmail.com');
            if ($devUser === null) {
                $devUser = $userRepo->findOneBy([], ['id' => 'ASC']);
            }

            if ($devUser !== null) {
                $session->set('user_id', $devUser->getId());
                $session->set('user_email', $devUser->getEmail());
                $session->set('user_name', $devUser->getFirstName());
                $session->set('user_roles', $devUser->getRoles());
                $userId = $devUser->getId();
                $roles = $devUser->getRoles();
            } else {
                return $this->redirectToRoute('app_login');
            }
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $this->redirectToRoute('back_dashboard');
        }

        return $this->redirectToRoute('front_home');
    }
}
