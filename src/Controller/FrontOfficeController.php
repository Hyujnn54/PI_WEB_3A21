<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontOfficeController extends AbstractController
{
    #[Route('/front', name: 'front_home')]
    #[Route('/home', name: 'app_home')]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');
        $roles = $session->get('user_roles', []);

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        // =============== CANDIDATE ===============
        if (in_array('ROLE_CANDIDATE', $roles)) {
            return $this->redirectToRoute('candidate_home');
        }

        // =============== RECRUITER ===============
        if (in_array('ROLE_RECRUITER', $roles)) {
            // TODO: Create recruiter_home later
            return $this->render('front/index.html.twig');
        }

        // =============== AUTHENTICATED DEFAULT ===============
        return $this->render('front/index.html.twig');
    }
}