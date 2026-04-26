<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontOfficeController extends AbstractController
{
    #[Route('/front', name: 'front_home')]
    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        if ($this->getUser() === null) {
            return $this->redirectToRoute('app_login');
        }

        // =============== CANDIDATE ===============
        if ($this->isGranted('ROLE_CANDIDATE')) {
            return $this->redirectToRoute('candidate_home');
        }

        // =============== RECRUITER ===============
        if ($this->isGranted('ROLE_RECRUITER')) {
            return $this->redirectToRoute('recruiter_home');
        }

        // =============== ADMIN ===============
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_front_home');
        }

        // =============== AUTHENTICATED DEFAULT ===============
        return $this->render('front/index.html.twig');
    }
}