<?php

namespace App\Controller;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TwoFactorSetupController extends AbstractController
{
    #[Route('/2fa/setup', name: 'app_2fa_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->isGoogleAuthenticatorEnabled()) {
            return $this->redirectToRoute('app_entry');
        }

        if (!$user->getGoogleAuthenticatorSecret()) {
            $user->setGoogleAuthenticatorSecret(TOTP::create(null, 30, 'sha1', 6, 0, null, 32)->getSecret());
            $entityManager->flush();
        }

        $secret = (string) $user->getGoogleAuthenticatorSecret();
        $totp = TOTP::create($secret, 30, 'sha1', 6);
        $totp->setIssuer('Talent Bridge');
        $totp->setLabel($user->getGoogleAuthenticatorUsername());

        $qrCodeDataUri = Builder::create()
            ->writer(new SvgWriter())
            ->data($totp->getProvisioningUri())
            ->size(300)
            ->margin(10)
            ->build()
            ->getDataUri();

        if ($request->isMethod('POST')) {
            $code = preg_replace('/\s+/', '', (string) $request->request->get('verification_code', ''));

            if (!preg_match('/^[0-9]{6}$/', $code)) {
                $this->addFlash('error', 'Enter the 6-digit code from your authenticator app.');
            } elseif (!$totp->verify($code, null, 1)) {
                $this->addFlash('error', 'The code is not valid. Scan the QR code again and try a fresh code.');
            } else {
                $user->setGoogleAuthenticatorEnabled(true);
                $entityManager->flush();

                $this->addFlash('success', 'Two-factor authentication is now enabled.');
                return $this->redirectToRoute('app_entry');
            }
        }

        return $this->render('security/two_factor_setup.html.twig', [
            'email' => $user->getEmail(),
            'secret' => $secret,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }
}