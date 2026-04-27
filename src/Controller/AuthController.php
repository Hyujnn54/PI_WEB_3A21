<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/', name: 'app_entry')]
    public function entry(): Response
    {
        if ($this->getUser() === null) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('back_dashboard');
        }

        return $this->redirectToRoute('front_home');
    }
}
