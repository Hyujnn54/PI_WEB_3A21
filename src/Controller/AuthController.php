<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/', name: 'app_entry')]
    public function entry(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');
        $roles = $session->get('user_roles', []);

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $this->redirectToRoute('back_dashboard');
        }

        return $this->redirectToRoute('front_home');
    }
}