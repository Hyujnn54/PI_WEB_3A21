<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Recruiter;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $hasher, 
        EntityManagerInterface $em,
        UsersRepository $userRepository
    ): Response {
        if ($request->isMethod('POST')) {
            // 1. DATA COLLECTION
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $phone = $request->request->get('phone');
            $role = $request->request->get('role');
            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');

            // 2. PHP VALIDATION (Contrôle de saisie)
            $errors = [];

            // Unique Email Check
            if ($userRepository->findOneBy(['email' => $email])) {
                $errors[] = "This email is already in use.";
            }

            // Phone Validation (8 Digits)
            if (!preg_match('/^[0-9]{8}$/', $phone)) {
                $errors[] = "The phone number must be exactly 8 digits.";
            }

            // Strong Password (Min 8 chars, 1 letter, 1 number)
            if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $errors[] = "Password must be at least 8 characters and contain both letters and numbers.";
            }

            // Redirect back if there are errors
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('auth/register.html.twig');
            }

            // 3. CREATE OBJECT BASED ON ROLE
            // This step automatically handles the "discr" column for the database
            $user = ($role === 'recruiter') ? new Recruiter() : new Candidate();

            // 4. SET COMMON DATA
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setPhone($phone);
            $user->setPassword($hasher->hashPassword($user, $password));

            // 5. ROLE-SPECIFIC LOGIC (instanceof)
            if ($user instanceof Recruiter) {
                $user->setCompanyName($request->request->get('company_name'));
                $user->setCompanyLocation($request->request->get('company_location'));
                $user->setRoles(['ROLE_RECRUITER']);
            } else {
                $user->setLocation($request->request->get('location'));
                $user->setEducationLevel($request->request->get('education_level'));
                $user->setExperienceYears((int)$request->request->get('experience_years'));
                $user->setRoles(['ROLE_CANDIDATE']);

                // Handle CV Upload
                $file = $request->files->get('cv_file');
                if ($file) {
                    $originalExtension = strtolower((string) $file->getClientOriginalExtension());
                    if ($originalExtension !== 'pdf') {
                        $this->addFlash('error', 'The CV must be a PDF file.');
                        return $this->render('auth/register.html.twig');
                    }

                    $destination = $this->getParameter('kernel.project_dir').'/public/uploads/cvs';
                    $fileName = uniqid('cv_', true).'.pdf';

                    try {
                        $file->move($destination, $fileName);
                        $user->setCvPath($fileName);
                    } catch (FileException) {
                        $this->addFlash('error', 'Unable to upload the CV file. Please try again.');
                        return $this->render('auth/register.html.twig');
                    }
                }
            }

            // 6. SAVE TO DATABASE
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Registration successful! You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig');
    }
}