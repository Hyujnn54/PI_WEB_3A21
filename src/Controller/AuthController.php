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
    public function entry(): Response
    {
        return $this->redirectToRoute('front_home');
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $fullName = trim((string) $request->request->get('full_name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $role = (string) $request->request->get('role', 'candidate');

            $errors = $this->validateAuthInput($fullName, $email, ['admin', 'candidate', 'recruiter'], $role);
            if (count($errors) > 0) {
                foreach ($errors as $errorMessage) {
                    $this->addFlash('error', $errorMessage);
                }
                return $this->redirectToRoute('app_login');
            }

            return $this->redirectByRole($role);
        }

        return $this->render('auth/login.html.twig');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $fullName = trim((string) $request->request->get('full_name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $role = (string) $request->request->get('role', 'candidate');

            $errors = $this->validateAuthInput($fullName, $email, ['candidate', 'recruiter'], $role);
            if (count($errors) > 0) {
                foreach ($errors as $errorMessage) {
                    $this->addFlash('error', $errorMessage);
                }
                return $this->redirectToRoute('app_register');
            }

            $this->addFlash('success', 'Registration completed successfully.');
            return $this->redirectByRole($role);
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): RedirectResponse
    {
        return $this->redirectToRoute('app_login');
    }

    private function redirectByRole(string $role): RedirectResponse
    {
        if ($role === 'admin') {
            return $this->redirectToRoute('back_dashboard');
        }

        return $this->redirectToRoute('front_home');
    }

    /**
     * @param string[] $allowedRoles
     * @return string[]
     */
    private function validateAuthInput(string $fullName, string $email, array $allowedRoles, string $role): array
    {
        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        } elseif (strlen($fullName) < 3 || strlen($fullName) > 120) {
            $errors[] = 'Full name must be between 3 and 120 characters.';
        }

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email format is invalid.';
        } elseif (strlen($email) > 190) {
            $errors[] = 'Email is too long.';
        }

        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Selected role is invalid.';
        }

        return $errors;
    }
}
