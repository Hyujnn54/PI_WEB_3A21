<?php

namespace App\Service\Translation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqLanguageDetector
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:GROQ_API_KEY)%')]
        private readonly string $groqApiKey
    ) {
    }

    /**
     * @param string[] $allowedLanguages
     */
    public function detectLanguageCode(string $text, array $allowedLanguages = ['en', 'fr', 'ar']): string
    {
        $content = trim($text);
        if ($content === '') {
            throw new \RuntimeException('Cover letter is empty and cannot be detected.');
        }

        $apiKey = trim($this->groqApiKey);
        if ($apiKey === '') {
            throw new \RuntimeException('Language detection is not configured. Please set GROQ_API_KEY.');
        }

        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'temperature' => 0,
                'max_tokens' => 20,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Detect the language of the provided text. Reply with only one ISO 639-1 code among: en, fr, ar.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ],
            'timeout' => 25,
        ]);

        $payload = $response->toArray(false);
        $errorMessage = trim((string) ($payload['error']['message'] ?? ''));
        if ($errorMessage !== '') {
            throw new \RuntimeException('Groq API error: ' . $errorMessage);
        }

        $raw = strtolower(trim((string) ($payload['choices'][0]['message']['content'] ?? '')));
        $raw = preg_replace('/[^a-z]/', '', $raw) ?? $raw;

        if ($raw === 'eng') {
            $raw = 'en';
        }

        if ($raw === 'fra' || $raw === 'fre') {
            $raw = 'fr';
        }

        if ($raw === 'ara') {
            $raw = 'ar';
        }

        if (in_array($raw, $allowedLanguages, true)) {
            return $raw;
        }

        // Safe fallback when model output is unexpected.
        if (preg_match('/\p{Arabic}/u', $content) === 1 && in_array('ar', $allowedLanguages, true)) {
            return 'ar';
        }

        if (in_array('en', $allowedLanguages, true)) {
            return 'en';
        }

        return $allowedLanguages[0] ?? 'en';
    }
}
