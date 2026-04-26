<?php

namespace App\Service\Translation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LibreTranslateClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:LIBRETRANSLATE_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(default::LIBRETRANSLATE_API_KEY)%')]
        private readonly ?string $apiKey = null
    ) {
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $content = trim($text);
        if ($content === '') {
            throw new \RuntimeException('Cover letter is empty and cannot be translated.');
        }

        $endpoint = rtrim(trim($this->baseUrl), '/') . '/translate';
        if ($endpoint === '/translate') {
            throw new \RuntimeException('LibreTranslate is not configured. Please set LIBRETRANSLATE_BASE_URL.');
        }

        $payload = [
            'q' => $content,
            'source' => $sourceLanguage,
            'target' => $targetLanguage,
            'format' => 'text',
        ];

        $key = trim((string) $this->apiKey);
        if ($key !== '') {
            $payload['api_key'] = $key;
        }

        $response = $this->httpClient->request('POST', $endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 30,
        ]);

        $result = $response->toArray(false);

        $error = trim((string) ($result['error'] ?? $result['message'] ?? ''));
        if ($error !== '') {
            throw new \RuntimeException('LibreTranslate error: ' . $error);
        }

        $translated = trim((string) ($result['translatedText'] ?? ''));
        if ($translated === '') {
            throw new \RuntimeException('Translation failed. Please try again.');
        }

        return $translated;
    }
}
