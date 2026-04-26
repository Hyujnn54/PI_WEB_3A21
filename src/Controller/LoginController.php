<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_entry');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the firewall logout key.');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UsersRepository $usersRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $emailValue = trim((string) $request->request->get('email', ''));

            if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
                return $this->render('auth/forgot_password.html.twig', [
                    'email' => $emailValue,
                    'error' => 'Please enter a valid email address.',
                ]);
            }

            $user = $usersRepository->findOneBy(['email' => $emailValue]);
            if (!$user instanceof Users) {
                $this->addFlash('error', 'This email does not exist in our database.');
                return $this->render('auth/forgot_password.html.twig', [
                    'email' => $emailValue,
                    'error' => 'This email does not exist in our database.',
                ]);
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date_create('+5 minutes');

            $user->setForgetCode($code);
            $user->setForgetCodeExpires($expiresAt === false ? null : $expiresAt);
            $entityManager->flush();

            try {
                $fromAddress = (string) ($_ENV['MAIL_FROM_ADDRESS'] ?? 'talentbridge.app@gmail.com');

                $message = (new Email())
                    ->from($fromAddress)
                    ->to($emailValue)
                    ->subject('Talent Bridge password recovery code')
                    ->text(
                        "Hello,\n\n" .
                        "Your password reset code is: {$code}\n" .
                        "This code expires in 5 minutes.\n\n" .
                        "If you did not request this, you can ignore this email."
                    );

                $mailer->send($message);
            } catch (\Throwable) {
                $this->addFlash('error', 'Unable to send recovery email right now. Please try again.');
                return $this->redirectToRoute('app_forgot_password_verify', ['email' => $emailValue]);
            }

            $this->addFlash('success', 'A recovery code has been sent to your email.');
            return $this->redirectToRoute('app_forgot_password_verify', ['email' => $emailValue]);
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    #[Route('/forgot-password/verify', name: 'app_forgot_password_verify', methods: ['GET', 'POST'])]
    public function forgotPasswordVerify(
        Request $request,
        UsersRepository $usersRepository,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): Response {
        $emailValue = trim((string) $request->query->get('email', $request->request->get('email', '')));

        if ($request->isMethod('POST')) {
            $emailValue = trim((string) $request->request->get('email', ''));
            $code = trim((string) $request->request->get('code', ''));
            $newPassword = (string) $request->request->get('new_password', '');

            if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
                return $this->render('auth/forgot_password_verify.html.twig', [
                    'email' => $emailValue,
                    'error' => 'Please enter a valid email address.',
                ]);
            }

            if (!preg_match('/^[0-9]{6}$/', $code)) {
                $this->addFlash('error', 'Code must be exactly 6 digits.');
                return $this->render('auth/forgot_password_verify.html.twig', [
                    'email' => $emailValue,
                    'error' => 'Code must be exactly 6 digits.',
                ]);
            }

            $user = $usersRepository->findOneBy(['email' => $emailValue]);
            if (!$user instanceof Users) {
                $this->addFlash('error', 'Invalid recovery request.');
                return $this->render('auth/forgot_password_verify.html.twig', [
                    'email' => $emailValue,
                    'error' => 'Invalid recovery request.',
                ]);
            }

            $expiresAt = $user->getForgetCodeExpires();
            $now = date_create();
            $isExpired = !$expiresAt || !$now || $now > $expiresAt;
            if ((string) $user->getForgetCode() !== $code || $isExpired) {
                $this->addFlash('error', 'Invalid or expired recovery code.');
                return $this->render('auth/forgot_password_verify.html.twig', [
                    'email' => $emailValue,
                    'error' => 'Invalid or expired recovery code.',
                ]);
            }

            $user->setPlainPassword($newPassword);
            $passwordViolations = $validator->validateProperty($user, 'plainPassword');
            if (count($passwordViolations) > 0) {
                $messages = [];
                foreach ($passwordViolations as $violation) {
                    $messages[] = $violation->getMessage();
                }
                foreach (array_unique($messages) as $message) {
                    $this->addFlash('error', $message);
                }

                return $this->render('auth/forgot_password_verify.html.twig', [
                    'email' => $emailValue,
                    'error' => implode(' ', array_unique($messages)),
                ]);
            }

            $user->setPassword($hasher->hashPassword($user, $newPassword));
            $user->setPlainPassword(null);
            $user->setForgetCode(null);
            $user->setForgetCodeExpires(null);

            $entityManager->flush();
            $this->addFlash('success', 'Password changed successfully. You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/forgot_password_verify.html.twig', ['email' => $emailValue]);
    }
}