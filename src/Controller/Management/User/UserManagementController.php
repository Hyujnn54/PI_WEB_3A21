<?php

namespace App\Controller\Management\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserManagementController extends AbstractController
{
    #[Route('/admin/users', name: 'management_users')]
    public function index(Request $request): Response
    {
        $authUser = $request->getSession()->get('auth_user');
        if (!$authUser) {
            return $this->redirectToRoute('app_login');
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            return $this->redirectToRoute('front_home');
        }

        return $this->render('management/user/index.html.twig', [
            'module' => 'User Management',
            'description' => 'Manage admins, candidates, and recruiters.',
            'authUser' => $authUser,
        ]);
    }
}
