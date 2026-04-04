<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/', name: 'app_entry')]
    public function entry(Request $request): Response
    {
        $authUser = $request->getSession()->get('auth_user');

        if (!$authUser) {
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectByRole((string) ($authUser['role'] ?? 'candidate'));
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        $authUser = $request->getSession()->get('auth_user');
        if ($authUser) {
            return $this->redirectByRole((string) ($authUser['role'] ?? 'candidate'));
        }

        if ($request->isMethod('POST')) {
            $fullName = trim((string) $request->request->get('full_name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $role = (string) $request->request->get('role', 'candidate');

            if ($fullName === '' || $email === '') {
                $this->addFlash('error', 'Please fill all required fields.');
                return $this->redirectToRoute('app_login');
            }

            if (!in_array($role, ['admin', 'candidate', 'recruiter'], true)) {
                $role = 'candidate';
            }

            $request->getSession()->set('auth_user', [
                'full_name' => $fullName,
                'email' => $email,
                'role' => $role,
            ]);

            return $this->redirectByRole($role);
        }

        return $this->render('auth/login.html.twig');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $authUser = $request->getSession()->get('auth_user');
        if ($authUser) {
            return $this->redirectByRole((string) ($authUser['role'] ?? 'candidate'));
        }

        if ($request->isMethod('POST')) {
            $fullName = trim((string) $request->request->get('full_name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $role = (string) $request->request->get('role', 'candidate');

            if ($fullName === '' || $email === '') {
                $this->addFlash('error', 'Please fill all required fields.');
                return $this->redirectToRoute('app_register');
            }

            if (!in_array($role, ['candidate', 'recruiter'], true)) {
                $role = 'candidate';
            }

            $request->getSession()->set('auth_user', [
                'full_name' => $fullName,
                'email' => $email,
                'role' => $role,
            ]);

            $this->addFlash('success', 'Registration completed successfully.');
            return $this->redirectByRole($role);
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        $request->getSession()->remove('auth_user');
        $request->getSession()->invalidate();

        return $this->redirectToRoute('app_login');
    }

    private function redirectByRole(string $role): RedirectResponse
    {
        if ($role === 'admin') {
            return $this->redirectToRoute('back_dashboard');
        }

        return $this->redirectToRoute('front_home');
    }
}
