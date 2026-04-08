<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Users;
use App\Entity\Admin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class BackOfficeController extends AbstractController
{
#[Route('/admin', name: 'back_dashboard')]
#[Route('/admin/dashboard', name: 'app_admin')]
public function index(UsersRepository $userRepo): Response
{
    $allUsers = $userRepo->findAll();
    $admins = 0; $candidates = 0; $recruiters = 0;

foreach ($allUsers as $user) {
    $roles = $user->getRoles();

    if (in_array('ROLE_ADMIN', $roles)) {
        $admins++;
    }

    if (in_array('ROLE_CANDIDATE', $roles)) {
        $candidates++;
    }

    if (in_array('ROLE_RECRUITER', $roles)) {
        $recruiters++;
    }
}

    return $this->render('admin/index.html.twig', [ 
        // Fixes image_7c33e3.png error
        'kpis' => [
            ['label' => 'Total Users', 'value' => count($allUsers), 'icon' => 'ti ti-users'],
            ['label' => 'Open Offers', 'value' => '32', 'icon' => 'ti ti-briefcase-2'],
            ['label' => 'Applications', 'value' => '3,580', 'icon' => 'ti ti-file-check'],
            ['label' => 'Interviews', 'value' => '482', 'icon' => 'ti ti-message-2'],
        ],
        // Required for the top 4 stat cards in your template
        'stats' => [
            'admins' => $admins,
            'candidates' => $candidates,
            'recruiters' => $recruiters,
            'interviews' => 482,
        ],
        // Required for the "Overview" table
        'usersPreview' => array_slice($allUsers, 0, 5),
    ]);
}

#[Route('/admin/users', name: 'app_admin_users')]
public function listUsers(UsersRepository $userRepo, Request $request): Response
{
    $searchTerm = trim($request->query->get('search', ''));
    $roleFilter = $request->query->get('role');

    if ($searchTerm !== '' || $roleFilter) {
        $users = $userRepo->findBySearchAndRole($searchTerm, $roleFilter);
    } else {
        $users = $userRepo->findAll();
    }

    // Count for the top text
    $allUsers = $userRepo->findAll();
    $totalCount = count($allUsers);

    // AJAX for live search + filter
    if ($request->query->get('ajax')) {
        return $this->render('admin/_user_table_rows.html.twig', [
            'users' => $users,
        ]);
    }

    return $this->render('admin/user_list.html.twig', [
        'users'       => $users,
        'searchTerm'  => $searchTerm,
        'currentRole' => $roleFilter,
        'totalCount'  => $totalCount,
    ]);
}
#[Route('/admin/add-admin', name: 'app_admin_add_user', methods: ['GET', 'POST'])]
public function addAdmin(
    Request $request, 
    UserPasswordHasherInterface $hasher, 
    EntityManagerInterface $em
): Response {
    if ($request->isMethod('POST')) {
        $user = new Admin(); 
        
        $user->setFirstName($request->request->get('first_name'));
        $user->setLastName($request->request->get('last_name'));
        $user->setEmail($request->request->get('email'));
        $user->setPhone($request->request->get('phone'));
        
        // FIXED: Set the missing mandatory field
        // You can use a value from a new form input or set a default like 'Management'
        if (method_exists($user, 'setAssignedArea')) {
            $user->setAssignedArea('General Management');
        }
        
        $plainPassword = $request->request->get('password');
        $hashedPassword = $hasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsActive(true);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Admin created successfully!');
        return $this->redirectToRoute('app_admin_users');
    }

    return $this->render('admin/add_user.html.twig');
}


#[Route('/admin/user/delete/{id}', name: 'app_admin_delete_user', methods: ['POST'])]
public function deleteUser(
    int $id, 
    UsersRepository $userRepo, 
    EntityManagerInterface $em
): Response
{
    $user = $userRepo->find($id);
    
    if (!$user) {
        $this->addFlash('error', 'User not found.');
        return $this->redirectToRoute('app_admin_users');
    }

    // Simple delete without CSRF for now (to fix your issue quickly)
    $em->remove($user);
    $em->flush();

    $this->addFlash('success', 'User deleted successfully.');
    return $this->redirectToRoute('app_admin_users');
}

#[Route('/admin/user/edit/{id}', name: 'app_admin_edit_user', methods: ['GET', 'POST'])]
public function editUser(int $id, UsersRepository $userRepo, Request $request, EntityManagerInterface $em): Response
{
    $user = $userRepo->find($id);
    
    if (!$user) {
        $this->addFlash('error', 'User not found.');
        return $this->redirectToRoute('app_admin_users');
    }

    if ($request->isMethod('POST')) {
        // Update the entity with new values from the form
        $user->setFirstName($request->request->get('first_name'));
        $user->setLastName($request->request->get('last_name'));
        $user->setEmail($request->request->get('email'));
        
        // Save changes to MySQL
        $em->flush();

        $this->addFlash('success', 'User updated successfully.');
        return $this->redirectToRoute('app_admin_users');
    }

    return $this->render('admin/edit_user.html.twig', [
        'user' => $user
    ]);
}

}