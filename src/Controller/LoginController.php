<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, UsersRepository $userRepo, UserPasswordHasherInterface $hasher): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            $user = $userRepo->findOneValidByEmail((string) $email);

            if ($user && $hasher->isPasswordValid($user, $password)) {
                
                // 1. SET THE SESSION DATA
                $session = $request->getSession();
                $session->set('user_id', $user->getId());
                $session->set('user_email', $user->getEmail());
                $session->set('user_name', $user->getFirstName());
                
                $roles = $user->getRoles(); // This gets the array from your DB
                $session->set('user_roles', $roles);

                $this->addFlash('success', 'Welcome back, ' . $user->getFirstName());

                // 2. REDIRECT BASED ON ROLE
                if (in_array('ROLE_ADMIN', $roles)) {
                    // Redirect to the Admin Dashboard
                    return $this->redirectToRoute('back_dashboard');
                } 
                
                if (in_array('ROLE_RECRUITER', $roles)) {
                    // Redirect to the Recruiter home/portal
                    return $this->redirectToRoute('front_home');
                }

                // Default: Redirect Candidates to the main home or job offers
                return $this->redirectToRoute('front_home');
            }

            $this->addFlash('error', 'Invalid email or password.');
        }

        return $this->render('auth/login.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        return $this->redirectToRoute('app_login');
    }
}