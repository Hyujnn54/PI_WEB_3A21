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

        try {
            $response = $this->httpClient->request('POST', trim($this->endpoint), [
                'body' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
            $result = is_array($data['result'] ?? null) ? $data['result'] : [];
            $error = (string) ($result['error'] ?? '');
            $sent = (string) ($result['sent'] ?? '');

            if ($statusCode >= 200 && $statusCode < 300) {
                $isErrorFree = $error === '' || $error === '0';
                $isSent = $sent === '' || $sent === '1';
                if ($isErrorFree && $isSent) {
                    return true;
                }
            }

            $this->logger->warning('SMS Mobile API request failed.', [
                'statusCode' => $statusCode,
                'error' => $error,
                'sent' => $sent,
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('SMS Mobile API request threw an exception.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return false;
    }
}
