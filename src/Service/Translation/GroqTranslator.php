<?php

namespace App\Service\Translation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqTranslator
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:GROQ_API_KEY)%')]
        private readonly string $groqApiKey
    ) {
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $content = trim($text);
        if ($content === '') {
            throw new \RuntimeException('Cover letter is empty and cannot be translated.');
        }

        $apiKey = trim($this->groqApiKey);
        if ($apiKey === '') {
            throw new \RuntimeException('Groq translation is not configured. Please set GROQ_API_KEY.');
        }

        $sourceLabel = $this->labelForLanguageCode($sourceLanguage);
        $targetLabel = $this->labelForLanguageCode($targetLanguage);

        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'temperature' => 0.2,
                'max_tokens' => 1400,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator. Return only the translated text. Do not add notes, comments, or quotation marks.',
                    ],
                    [
                        'role' => 'user',
                        'content' => implode("\n", [
                            'Translate the following cover letter from ' . $sourceLabel . ' to ' . $targetLabel . '.',
                            'Keep formatting and paragraph breaks when possible.',
                            'Return only the translation.',
                            '',
                            $content,
                        ]),
                    ],
                ],
            ],
            'timeout' => 30,
        ]);

        $payload = $response->toArray(false);
        $errorMessage = trim((string) ($payload['error']['message'] ?? ''));
        if ($errorMessage !== '') {
            throw new \RuntimeException('Groq API error: ' . $errorMessage);
        }

        $translated = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
        if ($translated === '') {
            throw new \RuntimeException('Groq translation failed. Please try again.');
        }

        return $translated;
    }

    private function labelForLanguageCode(string $languageCode): string
    {
        return match (strtolower(trim($languageCode))) {
            'en' => 'English',
            'fr' => 'French',
            'ar' => 'Arabic',
            default => strtoupper(trim($languageCode)),
        };
    }
}
