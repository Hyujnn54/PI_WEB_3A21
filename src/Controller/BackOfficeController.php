<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
<<<<<<< Updated upstream
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Recruitment_event;
use App\Entity\Recruiter;
=======
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
>>>>>>> Stashed changes

class BackOfficeController extends AbstractController
{
    #[Route('/admin', name: 'back_dashboard')]
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'authUser' => ['role' => 'admin'],
            'kpis' => [
                ['label' => 'Total Users', 'value' => '1,378', 'icon' => 'ti ti-users'],
                ['label' => 'Open Offers', 'value' => '32', 'icon' => 'ti ti-briefcase-2'],
                ['label' => 'Applications', 'value' => '3,580', 'icon' => 'ti ti-file-check'],
                ['label' => 'Interviews', 'value' => '482', 'icon' => 'ti ti-message-2'],
            ],
        ]);
    }

    #[Route('/admin/add-user', name: 'app_admin_add_user')]
    public function addUser(): Response
    {
        return $this->render('admin/add_user.html.twig', [
            'authUser' => ['role' => 'admin'],
        ]);
    }

    #[Route('/recruiter/create-event', name: 'recruiter_create_event', methods: ['GET', 'POST'])]
    public function createEvent(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $errors = [];
<<<<<<< Updated upstream
        
        if ($request->isMethod('POST')) {
            // Collect and validate input
            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));
            $eventType = trim($request->request->get('event_type', ''));
            $location = trim($request->request->get('location', ''));
            $eventDate = $request->request->get('event_date', '');
            $capacity = $request->request->get('capacity', '');
            $meetLink = trim($request->request->get('meet_link', ''));

            // Validation rules
            if (empty($title)) {
                $errors['title'] = 'Event title is required.';
            } elseif (strlen($title) < 3) {
                $errors['title'] = 'Event title must be at least 3 characters.';
            } elseif (strlen($title) > 255) {
                $errors['title'] = 'Event title cannot exceed 255 characters.';
            }

            if (empty($description)) {
                $errors['description'] = 'Description is required.';
            } elseif (strlen($description) < 10) {
                $errors['description'] = 'Description must be at least 10 characters.';
            }

            if (empty($eventType)) {
                $errors['event_type'] = 'Event type is required.';
            } elseif (!in_array($eventType, ['Workshop', 'Hiring Day', 'Webinar'])) {
                $errors['event_type'] = 'Invalid event type selected.';
            }

            if (empty($location)) {
                $errors['location'] = 'Location is required.';
            } elseif (strlen($location) < 2) {
                $errors['location'] = 'Location must be at least 2 characters.';
            }

            if (empty($eventDate)) {
                $errors['event_date'] = 'Event date is required.';
            } else {
                try {
                    $date = new \DateTime($eventDate);
                    $now = new \DateTime();
                    if ($date <= $now) {
                        $errors['event_date'] = 'Event date must be in the future.';
                    }
                } catch (\Exception $e) {
=======
        $formData = [
            'title' => '',
            'description' => '',
            'event_type' => '',
            'location' => '',
            'event_date' => '',
            'capacity' => '50',
            'meet_link' => '',
        ];

        if ($request->isMethod('POST')) {
            $formData = [
                'title' => trim((string) $request->request->get('title', '')),
                'description' => trim((string) $request->request->get('description', '')),
                'event_type' => trim((string) $request->request->get('event_type', '')),
                'location' => trim((string) $request->request->get('location', '')),
                'event_date' => (string) $request->request->get('event_date', ''),
                'capacity' => trim((string) $request->request->get('capacity', '')),
                'meet_link' => trim((string) $request->request->get('meet_link', '')),
            ];

            $event = new Recruitment_event();
            $event->setRecruiter_id($currentRecruiter);
            $event->setTitle($formData['title']);
            $event->setDescription($formData['description']);
            $event->setEvent_type($formData['event_type']);
            $event->setLocation($formData['location']);
            $event->setMeet_link($formData['meet_link']);
            $event->setCreated_at(new \DateTime());

            if ($formData['event_date'] !== '') {
                try {
                    $event->setEvent_date(new \DateTime($formData['event_date']));
                } catch (\Exception) {
>>>>>>> Stashed changes
                    $errors['event_date'] = 'Invalid date format.';
                }
            }

<<<<<<< Updated upstream
            if (empty($capacity)) {
                $errors['capacity'] = 'Capacity is required.';
            } else {
                $capacityInt = (int)$capacity;
                if ($capacityInt < 1) {
                    $errors['capacity'] = 'Capacity must be at least 1.';
                } elseif ($capacityInt > 1000) {
                    $errors['capacity'] = 'Capacity cannot exceed 1000.';
                }
            }

            if (!empty($meetLink) && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
                $errors['meet_link'] = 'Please enter a valid URL.';
            }

            // If no errors, save the event
            if (empty($errors)) {
                $recruiter = $entityManager->getRepository(Recruiter::class)->findOneBy([]);
                if (!$recruiter) {
                    $this->addFlash('error', 'No recruiter account was found. Please create a recruiter record first.');
                    return $this->redirectToRoute('recruiter_create_event');
                }

                $event = new Recruitment_event();
                $event->setId((string) mt_rand(10000000, 99999999));
                $event->setRecruiter_id($recruiter);
                $event->setTitle($title);
                $event->setDescription($description);
                $event->setEvent_type($eventType);
                $event->setLocation($location);
                $event->setEvent_date(new \DateTime($eventDate));
                $event->setCapacity((int)$capacity);
                $event->setMeet_link($meetLink);
                $event->setCreated_at(new \DateTime());

=======
            if ($formData['capacity'] !== '' && ctype_digit($formData['capacity'])) {
                $event->setCapacity((int) $formData['capacity']);
            }

            foreach ($validator->validate($event) as $violation) {
                $field = (string) $violation->getPropertyPath();
                if (!isset($errors[$field])) {
                    $errors[$field] = (string) $violation->getMessage();
                }
            }

            if ($errors === []) {
>>>>>>> Stashed changes
                $entityManager->persist($event);
                $entityManager->flush();

                $this->addFlash('success', 'Event created successfully!');
                return $this->redirectToRoute('front_events');
            }
        }

        return $this->render('back/create_event.html.twig', [
            'authUser' => ['role' => 'recruiter'],
            'errors' => $errors,
<<<<<<< Updated upstream
=======
            'isEdit' => false,
            'formData' => $formData,
>>>>>>> Stashed changes
        ]);
    }

    #[Route('/recruiter/events/generate-description', name: 'recruiter_generate_event_description', methods: ['POST'])]
    public function generateEventDescription(Request $request, EntityManagerInterface $entityManager, HttpClientInterface $httpClient): JsonResponse
    {
        $currentRecruiter = $this->resolveCurrentRecruiter($request, $entityManager);
        if (!$currentRecruiter instanceof Recruiter) {
            return $this->json([
                'error' => 'No recruiter account is linked to your current session.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'error' => 'Invalid request payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $eventType = trim((string) ($payload['event_type'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $eventDate = trim((string) ($payload['event_date'] ?? ''));
        $capacity = trim((string) ($payload['capacity'] ?? ''));

        if ($title === '' || $eventType === '' || $location === '' || $eventDate === '') {
            return $this->json([
                'error' => 'Please provide title, event type, location, and event date before generating.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $explicitAiApiKey = trim((string) ($_ENV['AI_API_KEY'] ?? $_SERVER['AI_API_KEY'] ?? ''));
        $apiKey = trim((string) (
            $explicitAiApiKey
            ?: ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '')
        ));

        $apiUrl = trim((string) (
            $_ENV['AI_CHAT_COMPLETIONS_URL']
            ?? $_SERVER['AI_CHAT_COMPLETIONS_URL']
            ?? ''
        ));

        if ($apiUrl === '') {
            // If a generic AI key is provided, default to OpenRouter's OpenAI-compatible endpoint.
            $hasGenericAiKey = trim((string) ($_ENV['AI_API_KEY'] ?? $_SERVER['AI_API_KEY'] ?? '')) !== '';
            $apiUrl = $hasGenericAiKey
                ? 'https://openrouter.ai/api/v1/chat/completions'
                : 'https://api.openai.com/v1/chat/completions';
        }

        if (trim((string) ($_ENV['AI_CHAT_COMPLETIONS_URL'] ?? $_SERVER['AI_CHAT_COMPLETIONS_URL'] ?? '')) !== '' && $explicitAiApiKey === '') {
            return $this->json([
                'error' => 'AI_CHAT_COMPLETIONS_URL is set, so AI_API_KEY must also be set for that provider.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $model = trim((string) (
            $_ENV['AI_MODEL']
            ?? $_SERVER['AI_MODEL']
            ?? $_ENV['OPENAI_MODEL']
            ?? $_SERVER['OPENAI_MODEL']
            ?? ''
        ));

        if ($model === '') {
            // Reliable default for OpenRouter that auto-selects available providers/models.
            $model = str_contains($apiUrl, 'openrouter.ai')
                ? 'openrouter/auto'
                : 'gpt-4o-mini';
        }

        if ($apiKey === '') {
            return $this->json([
                'error' => 'AI_API_KEY (or OPENAI_API_KEY) is not configured on the server.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $userPrompt = sprintf(
            "Generate a professional recruitment event description in plain text (90-140 words).\\nTitle: %s\\nEvent type: %s\\nLocation: %s\\nDate: %s\\nCapacity: %s\\n\\nRules:\\n- Keep it concise and attractive for candidates.\\n- Mention benefits and expected outcomes.\\n- No markdown, no emojis, no bullet points.",
            $title,
            $eventType,
            $location,
            $eventDate,
            $capacity !== '' ? $capacity : 'Not specified'
        );

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ];

            if (str_contains($apiUrl, 'openrouter.ai')) {
                $headers['HTTP-Referer'] = $request->getSchemeAndHttpHost();
                $headers['X-Title'] = 'Talent Bridge Event Description Generator';
            }

            $requestCompletion = static function (HttpClientInterface $client, string $url, array $headersPayload, string $modelName, string $prompt) {
                return $client->request('POST', $url, [
                    'headers' => $headersPayload,
                    'json' => [
                        'model' => $modelName,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a recruitment copywriter for event pages.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                        'temperature' => 0.7,
                    ],
                    'timeout' => 20,
                ]);
            };

            $apiResponse = $requestCompletion($httpClient, $apiUrl, $headers, $model, $userPrompt);

            $statusCode = $apiResponse->getStatusCode();
            $result = $apiResponse->toArray(false);

            $apiError = trim((string) ($result['error']['message'] ?? ''));
            $shouldRetryWithAuto = $statusCode >= 400
                && str_contains($apiUrl, 'openrouter.ai')
                && stripos($apiError, 'no endpoints found') !== false
                && strtolower($model) !== 'openrouter/auto';

            if ($shouldRetryWithAuto) {
                $apiResponse = $requestCompletion($httpClient, $apiUrl, $headers, 'openrouter/auto', $userPrompt);
                $statusCode = $apiResponse->getStatusCode();
                $result = $apiResponse->toArray(false);
                $apiError = trim((string) ($result['error']['message'] ?? ''));
            }

            if ($statusCode >= 400) {
                if ($apiError === '') {
                    $apiError = 'AI provider request failed with status ' . $statusCode . '.';
                }

                return $this->json([
                    'error' => $apiError,
                ], Response::HTTP_BAD_GATEWAY);
            }

            $rawContent = $result['choices'][0]['message']['content'] ?? '';
            $description = '';

            if (is_string($rawContent)) {
                $description = trim($rawContent);
            } elseif (is_array($rawContent)) {
                $parts = [];
                foreach ($rawContent as $part) {
                    if (is_array($part) && ($part['type'] ?? '') === 'text') {
                        $parts[] = trim((string) ($part['text'] ?? ''));
                    }
                }
                $description = trim(implode("\n", array_filter($parts, static fn (string $value): bool => $value !== '')));
            }

            if ($description === '') {
                return $this->json([
                    'error' => 'The AI service returned no usable text. Please try again.',
                ], Response::HTTP_BAD_GATEWAY);
            }

            return $this->json([
                'description' => $description,
            ]);
        } catch (\Throwable) {
            return $this->json([
                'error' => 'Unable to generate description right now. Please try again in a moment.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/recruiter/delete-event/{id}', name: 'recruiter_delete_event', methods: ['POST'])]
    public function deleteEvent(int $id, EntityManagerInterface $entityManager): Response
    {
        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $entityManager->remove($event);
        $entityManager->flush();

        $this->addFlash('success', 'Event deleted successfully!');
        return $this->redirectToRoute('front_events');
    }

    #[Route('/recruiter/update-event/{id}', name: 'recruiter_update_event', methods: ['POST'])]
    public function updateEvent(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = $entityManager->getRepository(Recruitment_event::class)->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $errors = [];
        $title = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));
        $eventType = trim($request->request->get('event_type', ''));
        $location = trim($request->request->get('location', ''));
        $eventDate = $request->request->get('event_date', '');
        $capacity = $request->request->get('capacity', '');
        $meetLink = trim($request->request->get('meet_link', ''));

        if (empty($title)) {
            $errors['title'] = 'Event title is required.';
        } elseif (strlen($title) < 3) {
            $errors['title'] = 'Event title must be at least 3 characters.';
        } elseif (strlen($title) > 255) {
            $errors['title'] = 'Event title cannot exceed 255 characters.';
        }

        if (empty($description)) {
            $errors['description'] = 'Description is required.';
        } elseif (strlen($description) < 10) {
            $errors['description'] = 'Description must be at least 10 characters.';
        }

        if (empty($eventType)) {
            $errors['event_type'] = 'Event type is required.';
        } elseif (!in_array($eventType, ['Workshop', 'Hiring Day', 'Webinar'])) {
            $errors['event_type'] = 'Invalid event type selected.';
        }

        if (empty($location)) {
            $errors['location'] = 'Location is required.';
        } elseif (strlen($location) < 2) {
            $errors['location'] = 'Location must be at least 2 characters.';
        }

        if (empty($eventDate)) {
            $errors['event_date'] = 'Event date is required.';
        } else {
            try {
                $date = new \DateTime($eventDate);
                $now = new \DateTime();
                if ($date <= $now) {
                    $errors['event_date'] = 'Event date must be in the future.';
                }
            } catch (\Exception $e) {
                $errors['event_date'] = 'Invalid date format.';
            }
        }

        if (empty($capacity)) {
            $errors['capacity'] = 'Capacity is required.';
        } else {
            $capacityInt = (int)$capacity;
            if ($capacityInt < 1) {
                $errors['capacity'] = 'Capacity must be at least 1.';
            } elseif ($capacityInt > 1000) {
                $errors['capacity'] = 'Capacity cannot exceed 1000.';
            }
        }

        if (!empty($meetLink) && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
            $errors['meet_link'] = 'Please enter a valid URL.';
        }

        if (!empty($errors)) {
            $this->addFlash('warning', 'Event could not be updated. Please fix the errors.');
            return $this->redirectToRoute('front_events', ['role' => 'recruiter']);
        }

        $event->setTitle($title);
        $event->setDescription($description);
        $event->setEvent_type($eventType);
        $event->setLocation($location);
        $event->setEvent_date(new \DateTime($eventDate));
        $event->setCapacity((int)$capacity);
        $event->setMeet_link($meetLink);

        $entityManager->flush();

        $this->addFlash('success', 'Event updated successfully!');
        return $this->redirectToRoute('front_events');
    }
}
