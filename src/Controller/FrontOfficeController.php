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
        $role = (string) $request->query->get('role', 'candidate');
        if (!in_array($role, ['candidate', 'recruiter'], true)) {
            $role = 'candidate';
        }

        return $this->render('front/index.html.twig', [
            'authUser' => ['role' => $role],
        ]);
    }
}
