<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Security\LoginFormAuthenticator;
use App\Service\LuxandFaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class FaceAuthController extends AbstractController
{
    #[Route('/profile/face/enable', name: 'app_face_enable', methods: ['POST'])]
    public function enableFaceLogin(
        Request $request,
        LuxandFaceService $luxand,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('face_enable', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Invalid face upload request. Please try again.');
            return $this->redirectToRoute('front_profile');
        }

        $photo = $request->files->get('face_photo');
        if (!$photo instanceof UploadedFile || !$photo->isValid()) {
            $this->addFlash('error', 'Please choose a clear face image first.');
            return $this->redirectToRoute('front_profile');
        }

        $mimeType = (string) ($photo->getMimeType() ?? '');
        if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            $this->addFlash('error', 'Face image must be JPG or PNG.');
            return $this->redirectToRoute('front_profile');
        }

        try {
            $personId = (string) $user->getFacePersonId();
            if ($personId === '') {
                $personId = $luxand->addPerson((string) $user->getEmail(), $photo);
                $user->setFacePersonId($personId);
            } else {
                $luxand->addFace($personId, $photo);
            }

            $user->setFaceEnabled(true);
            $entityManager->flush();

            $this->addFlash('success', 'Face login enabled successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Unable to enable face login right now. ' . $e->getMessage());
        }

        return $this->redirectToRoute('front_profile');
    }

    #[Route('/profile/face/disable', name: 'app_face_disable', methods: ['POST'])]
    public function disableFaceLogin(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('face_disable', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Invalid disable request. Please try again.');
            return $this->redirectToRoute('front_profile');
        }

        $user->setFaceEnabled(false);
        $entityManager->flush();

        $this->addFlash('success', 'Face login disabled.');
        return $this->redirectToRoute('front_profile');
    }

    #[Route('/login/face', name: 'app_login_face', methods: ['POST'])]
    public function loginWithFace(
        Request $request,
        LuxandFaceService $luxand,
        UsersRepository $usersRepository,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('face_login', (string) $request->request->get('_token', ''))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid face-login token.'], 400);
        }

        $shots = $request->files->all('shots');
        if (!is_array($shots) || count($shots) === 0) {
            return new JsonResponse(['success' => false, 'error' => 'No camera frames were received.'], 400);
        }

        $bestShot = null;
        $bestLive = null;

        try {
            foreach ($shots as $shot) {
                if (!$shot) {
                    continue;
                }

                $live = $luxand->checkLiveness($shot);
                if ($bestLive === null || $live['score'] > $bestLive['score']) {
                    $bestLive = $live;
                    $bestShot = $shot;
                }

                if (($live['isReal'] ?? false) && ($live['score'] ?? 0) >= 0.70) {
                    $bestLive = $live;
                    $bestShot = $shot;
                    break;
                }
            }

            if ($bestLive === null || $bestShot === null) {
                return new JsonResponse(['success' => false, 'error' => 'Liveness check failed.'], 422);
            }

            $accepted = (bool) ($bestLive['isReal'] ?? false)
                || (float) ($bestLive['score'] ?? 0) >= $luxand->livenessThreshold();

            if (!$accepted) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Liveness validation failed. Try better lighting and slight movement.',
                ], 422);
            }

            $match = $luxand->searchBestMatch($bestShot);
            if ($match === null) {
                return new JsonResponse(['success' => false, 'error' => 'Face not recognized.'], 401);
            }

            if ((float) ($match['probability'] ?? 0) < $luxand->faceThreshold()) {
                return new JsonResponse(['success' => false, 'error' => 'Face match confidence is too low.'], 401);
            }

            $user = $usersRepository->findOneBy([
                'facePersonId' => (string) $match['uuid'],
                'faceEnabled' => true,
            ]);

            if (!$user instanceof Users) {
                return new JsonResponse(['success' => false, 'error' => 'This face is not linked to an active account.'], 401);
            }

            $response = $userAuthenticator->authenticateUser($user, $loginFormAuthenticator, $request);
            $redirect = $response instanceof RedirectResponse
                ? $response->getTargetUrl()
                : $this->generateUrl('app_entry');

            return new JsonResponse(['success' => true, 'redirect' => $redirect]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Face login unavailable right now. ' . $e->getMessage(),
            ], 500);
        }
    }
}
