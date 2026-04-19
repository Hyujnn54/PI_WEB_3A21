<?php

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Recruiter;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $hasher, 
        EntityManagerInterface $em,
        UsersRepository $userRepository,
        ValidatorInterface $validator
    ): Response {
        if ($request->isMethod('POST')) {
            // 1. DATA COLLECTION
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $phone = trim((string) $request->request->get('phone', ''));
            $role = trim((string) $request->request->get('role', 'candidate'));
            $firstName = trim((string) $request->request->get('first_name', ''));
            $lastName = trim((string) $request->request->get('last_name', ''));

            // 3. CREATE OBJECT BASED ON ROLE
            // This step automatically handles the "discr" column for the database
            $user = ($role === 'recruiter') ? new Recruiter() : new Candidate();

            // 4. SET COMMON DATA
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setPhone($phone);
            $user->setPlainPassword((string) $password);

            // 5. ROLE-SPECIFIC LOGIC (instanceof)
            if ($user instanceof Recruiter) {
                $user->setCompanyName(trim((string) $request->request->get('company_name', '')));
                $user->setCompanyLocation(trim((string) $request->request->get('company_location', '')));
                $user->setRoles(['ROLE_RECRUITER']);
            } else {
                $user->setLocation(trim((string) $request->request->get('location', '')));
                $user->setEducationLevel(trim((string) $request->request->get('education_level', '')));
                $experienceInput = trim((string) $request->request->get('experience_years', ''));
                $user->setExperienceYears($experienceInput === '' ? null : (int) $experienceInput);
                $user->setRoles(['ROLE_CANDIDATE']);

                // Handle CV Upload
                $file = $request->files->get('cv_file');
                if ($file) {
                    $destination = $this->getParameter('kernel.project_dir').'/public/uploads/cvs';
                    $fileName = uniqid().'.'.$file->guessExtension();
                    $file->move($destination, $fileName);
                    $user->setCvPath($fileName);
                }
            }

            // 6. Validate all business rules in PHP through entity constraints.
            $violations = $validator->validate($user);
            if (count($violations) > 0) {
                $messages = [];
                foreach ($violations as $violation) {
                    $messages[] = $violation->getMessage();
                }

                foreach (array_unique($messages) as $message) {
                    $this->addFlash('error', $message);
                }

                return $this->render('auth/register.html.twig');
            }

            $plainPassword = (string) $user->getPlainPassword();
            $user->setPassword($hasher->hashPassword($user, $plainPassword));
            $user->setPlainPassword(null);

            // 7. SAVE TO DATABASE
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Registration successful! You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig');
    }
}