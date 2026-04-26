<?php

namespace App\Service\JobApplication;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqCoverLetterGenerator
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
     * @param array{candidate_name: string, candidate_email: string, candidate_phone: string, candidate_location: string, education_level: string, experience_years: string, skills: string[], offer_title: string, offer_location: string, offer_contract: string, cv_text: string} $context
     */
    public function generate(array $context): string
    {
        $apiKey = trim($this->groqApiKey);
        if ($apiKey === '') {
            throw new \RuntimeException('Cover letter generator is not configured. Please set GROQ_API_KEY in your local environment.');
        }

        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'temperature' => 0.6,
                'max_tokens' => 700,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert career assistant. Generate one professional, concise cover letter in plain text only. No markdown, no bullet list, no extra commentary. The letter should be convincing and tailored to the job and candidate profile.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($context),
                    ],
                ],
            ],
            'timeout' => 45,
        ]);

        $payload = $response->toArray(false);
        $errorMessage = trim((string) ($payload['error']['message'] ?? ''));
        if ($errorMessage !== '') {
            throw new \RuntimeException('Groq API error: ' . $errorMessage);
        }

        $rawContent = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
        if ($rawContent === '') {
            throw new \RuntimeException('Could not generate a cover letter. Please try again.');
        }

        $normalized = $this->normalizeGeneratedText($rawContent);
        if (mb_strlen($normalized) < 50) {
            throw new \RuntimeException('Generated cover letter is too short. Please try again.');
        }

        return $normalized;
    }

    /**
     * @param array{candidate_name: string, candidate_email: string, candidate_phone: string, candidate_location: string, education_level: string, experience_years: string, skills: string[], offer_title: string, offer_location: string, offer_contract: string, cv_text: string} $context
     */
    private function buildPrompt(array $context): string
    {
        $skills = $context['skills'] === [] ? 'No skills listed in profile.' : implode(', ', $context['skills']);
        $cvText = trim($context['cv_text']);
        if ($cvText === '') {
            $cvText = 'No CV text available.';
        }

        return implode("\n", [
            'Generate a tailored job cover letter based on this data:',
            '',
            'Candidate profile:',
            '- Name: ' . $context['candidate_name'],
            '- Email: ' . $context['candidate_email'],
            '- Phone: ' . $context['candidate_phone'],
            '- Location: ' . $context['candidate_location'],
            '- Education: ' . $context['education_level'],
            '- Experience: ' . $context['experience_years'],
            '- Skills: ' . $skills,
            '',
            'Job offer:',
            '- Title: ' . $context['offer_title'],
            '- Location: ' . $context['offer_location'],
            '- Contract: ' . $context['offer_contract'],
            '',
            'CV extracted text:',
            $cvText,
            '',
            'Requirements:',
            '- Return only the final cover letter body in plain text.',
            '- Keep it between 130 and 260 words.',
            '- Mention relevant profile strengths and alignment with the offer.',
            '- Use a polite professional tone.',
        ]);
    }

    private function normalizeGeneratedText(string $text): string
    {
        $clean = str_replace(["\r\n", "\r"], "\n", $text);
        $clean = preg_replace('/```[\s\S]*?```/', '', $clean) ?? $clean;
        $clean = preg_replace('/^#+\s*/m', '', $clean) ?? $clean;
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;
        $clean = trim($clean);

        if (mb_strlen($clean) > 1900) {
            $clean = trim(mb_substr($clean, 0, 1900));
        }

        return $clean;
    }
}
