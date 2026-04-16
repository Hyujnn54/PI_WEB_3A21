<?php

namespace App\Service\Interview;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class SmsMobileApiSender
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:SMS_MOBILE_API_ENDPOINT)%')]
        private readonly string $endpoint,
        #[Autowire('%env(string:SMS_MOBILE_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(string:SMS_MOBILE_API_DEVICE_ID)%')]
        private readonly string $deviceId,
    ) {
    }

    public function isEnabled(): bool
    {
        return trim($this->endpoint) !== '' && trim($this->apiKey) !== '';
    }

    public function send(string $recipient, string $message): bool
    {
        if (trim($recipient) === '' || trim($message) === '') {
            return false;
        }

        if (!$this->isEnabled()) {
            $this->logger->warning('SMS Mobile API sender is disabled: missing endpoint or API key.');
            return false;
        }

        $payload = [
            'apikey' => trim($this->apiKey),
            'recipients' => $recipient,
            'message' => $message,
            'sendsms' => '1',
        ];

        $deviceId = trim($this->deviceId);
        if ($deviceId !== '') {
            $payload['sIdentifiant'] = $deviceId;
        }

        $this->logger->info('SMS Mobile API dispatch attempt.', [
            'recipient' => $recipient,
        ]);

        try {
            $response = $this->httpClient->request('POST', trim($this->endpoint), [
                'body' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
            $data = json_decode($responseBody, true);
            if (!is_array($data)) {
                $data = [];
            }
            $result = is_array($data['result'] ?? null) ? $data['result'] : [];
            $error = (string) ($result['error'] ?? '');
            $sent = (string) ($result['sent'] ?? '');

            if ($statusCode >= 200 && $statusCode < 300) {
                $isErrorFree = $error === '' || $error === '0';
                $isSent = $sent === '' || $sent === '1';
                if ($isErrorFree && $isSent) {
                    $this->logger->info('SMS Mobile API message sent.', [
                        'recipient' => $recipient,
                        'statusCode' => $statusCode,
                    ]);
                    return true;
                }
            }

            $this->logger->warning('SMS Mobile API request failed.', [
                'recipient' => $recipient,
                'statusCode' => $statusCode,
                'error' => $error,
                'sent' => $sent,
                'response' => $this->snippet($responseBody),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('SMS Mobile API request threw an exception.', [
                'recipient' => $recipient,
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
