<?php

namespace App\Service\Interview;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class BrevoEmailSender
{
    private const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:BREVO_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(string:BREVO_SENDER_EMAIL)%')]
        private readonly string $senderEmail,
        #[Autowire('%env(string:BREVO_SENDER_NAME)%')]
        private readonly string $senderName,
    ) {
    }

    public function isEnabled(): bool
    {
        return trim($this->apiKey) !== '' && (bool) filter_var($this->senderEmail, FILTER_VALIDATE_EMAIL);
    }

    public function send(string $toEmail, string $toName, string $subject, string $textContent, string $htmlContent = ''): bool
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!$this->isEnabled()) {
            $this->logger->warning('Brevo email sender is disabled: missing API key or sender email.');
            return false;
        }

        $to = ['email' => $toEmail];
        $trimmedName = trim($toName);
        if ($trimmedName !== '') {
            $to['name'] = $trimmedName;
        }

        $payload = [
            'sender' => [
                'email' => $this->senderEmail,
                'name' => trim($this->senderName) !== '' ? trim($this->senderName) : 'Talent Bridge',
            ],
            'to' => [$to],
            'subject' => $subject,
            'textContent' => $textContent,
        ];

        if (trim($htmlContent) !== '') {
            $payload['htmlContent'] = $htmlContent;
        }

        $this->logger->info('Brevo email dispatch attempt.', [
            'toEmail' => $toEmail,
            'subject' => $subject,
        ]);

        try {
            $response = $this->httpClient->request('POST', self::BREVO_ENDPOINT, [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => trim($this->apiKey),
                    'content-type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
            $responseData = json_decode($responseBody, true);
            $messageId = is_array($responseData) ? (string) ($responseData['messageId'] ?? '') : '';

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Brevo email sent.', [
                    'toEmail' => $toEmail,
                    'statusCode' => $statusCode,
                    'messageId' => $messageId,
                ]);
                return true;
            }

            $this->logger->warning('Brevo email request failed.', [
                'toEmail' => $toEmail,
                'statusCode' => $statusCode,
                'messageId' => $messageId,
                'response' => $this->snippet($responseBody),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Brevo email request threw an exception.', [
                'toEmail' => $toEmail,
                'message' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    private function snippet(string $value, int $maxLength = 500): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength) . '...';
    }
}
