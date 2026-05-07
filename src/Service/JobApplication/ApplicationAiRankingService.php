<?php

namespace App\Service\JobApplication;

use App\Entity\Candidate;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Repository\Candidate_skillRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApplicationAiRankingService
{
    private const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    private const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const OPENROUTER_MAX_TOKENS = 700;
    private const GROQ_MAX_TOKENS = 320;

    private const WEIGHTS = [
        'skill_match' => 0.50,
        'experience_relevance' => 0.20,
        'education_fit' => 0.10,
        'cover_letter_relevance' => 0.10,
        'cv_relevance' => 0.10,
    ];

    /**
     * @param Job_application[] $applications
     *
     * @return array{results: array<string, array<string, mixed>>, errors: string[]}
     */
    public function rankApplications(array $applications): array
    {
        $results = [];
        $errors = [];

        foreach ($applications as $application) {
            $applicationId = (string) $application->getId();

            try {
                $context = $this->buildContext($application);
                $raw = $this->callModel($context);
                $parsed = $this->parseModelPayload($raw);
                $results[$applicationId] = $this->buildResultFromParsedPayload($parsed, $context);
            } catch (\Throwable $exception) {
                $context = $this->buildContext($application);
                $results[$applicationId] = $this->heuristicFallback($context);
                $errors[] = sprintf('Application #%s: %s', $applicationId, $exception->getMessage());
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Job_application $application): array
    {
        $offer = $application->getOffer_id();
        $candidate = $application->getCandidate_id();

        $offerTitle = trim((string) $offer->getTitle());
        $offerDescription = trim((string) $offer->getDescription());
        $requiredSkills = $this->extractOfferSkills($offer);

        $candidateName = $this->resolveCandidateName($candidate);
        $candidateSkills = $this->extractCandidateSkills($candidate);
        $experienceYears = $candidate->getExperienceYears();
        $educationLevel = trim((string) $candidate->getEducationLevel());
        $coverLetter = trim((string) $application->getCover_letter());

        $applicationCvPath = trim((string) $application->getCv_path());
        $profileCvPath = trim((string) $candidate->getCvPath());
        $cvText = $this->extractCvText($applicationCvPath, $profileCvPath);

        return [
            'application_id' => (string) $application->getId(),
            'job_title' => $offerTitle,
            'job_description' => $this->limit($offerDescription, 2500),
            'required_skills' => $requiredSkills,
            'candidate_name' => $candidateName,
            'candidate_skills' => $candidateSkills,
            'experience_years' => $experienceYears,
            'education_level' => $educationLevel,
            'cover_letter' => $this->limit($coverLetter, 2500),
            'cv_text' => $this->limit($cvText, 3000),
            'inputs_used' => [
                'job title',
                'job description',
                'required skills',
                'candidate skills',
                'experience years',
                'education level',
                'cover letter text',
                'cv extracted text',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function callModel(array $context): string
    {
        $prompt = $this->buildPrompt($context);
        $systemInstruction = 'You are an objective recruiter assistant. Always return valid JSON only. No markdown, no prose around JSON.';
        $providerErrors = [];

        $openRouterKey = trim($this->openRouterApiKey);
        if ($openRouterKey !== '') {
            try {
                return $this->callProvider(
                    'OpenRouter',
                    self::OPENROUTER_ENDPOINT,
                    $openRouterKey,
                    trim($this->openRouterModel),
                    $systemInstruction,
                    $prompt,
                    self::OPENROUTER_MAX_TOKENS,
                    1
                );
            } catch (\Throwable $exception) {
                $providerErrors[] = 'OpenRouter failed: ' . $exception->getMessage();
            }
        } else {
            $providerErrors[] = 'OpenRouter skipped: OPENROUTER_API_KEY is missing.';
        }

        $groqKey = trim($this->groqApiKey);
        if ($groqKey !== '') {
            try {
                $groqPrompt = $this->buildPrompt($this->reduceContextForGroq($context));

                return $this->callProvider(
                    'Groq',
                    self::GROQ_ENDPOINT,
                    $groqKey,
                    trim($this->groqModel),
                    $systemInstruction,
                    $groqPrompt,
                    self::GROQ_MAX_TOKENS,
                    3
                );
            } catch (\Throwable $exception) {
                $providerErrors[] = 'Groq failed: ' . $exception->getMessage();
            }
        } else {
            $providerErrors[] = 'Groq skipped: GROQ_API_KEY is missing.';
        }

        throw new \RuntimeException(implode(' ', $providerErrors));
    }

    private function callProvider(
        string $providerName,
        string $endpoint,
        string $apiKey,
        string $model,
        string $systemInstruction,
        string $prompt,
        int $maxTokens,
        int $maxAttempts
    ): string {
        if ($model === '') {
            throw new \RuntimeException($providerName . ' model is missing.');
        }

        $attempt = 0;
        $lastError = $providerName . ' request failed for an unknown reason.';

        while ($attempt < $maxAttempts) {
            $attempt++;

            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.1,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemInstruction,
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
                'timeout' => 45,
            ]);

            $payload = $response->toArray(false);
            $statusCode = $response->getStatusCode();
            $errorMessage = trim((string) ($payload['error']['message'] ?? ''));

            if ($errorMessage === '') {
                $content = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
                if ($content !== '') {
                    return $content;
                }

                $errorMessage = $providerName . ' returned an empty completion.';
            }

            $lastError = $providerName . ' API error: ' . $errorMessage;

            if ($attempt < $maxAttempts && $this->isRateLimitError($statusCode, $errorMessage)) {
                $waitSeconds = $this->extractRetryAfterSeconds($errorMessage);
                usleep((int) round($waitSeconds * 1_000_000));
                continue;
            }

            throw new \RuntimeException($lastError);
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function reduceContextForGroq(array $context): array
    {
        $reduced = $context;
        $reduced['job_description'] = $this->limit((string) ($context['job_description'] ?? ''), 1400);
        $reduced['cover_letter'] = $this->limit((string) ($context['cover_letter'] ?? ''), 1200);
        $reduced['cv_text'] = $this->limit((string) ($context['cv_text'] ?? ''), 1300);

        return $reduced;
    }

    private function isRateLimitError(int $statusCode, string $errorMessage): bool
    {
        if ($statusCode === 429) {
            return true;
        }

        return preg_match('/rate\s*limit|tokens\s*per\s*minute|try\s*again\s*in/i', $errorMessage) === 1;
    }

    private function extractRetryAfterSeconds(string $errorMessage): float
    {
        if (preg_match('/try\s+again\s+in\s+([0-9]+(?:\.[0-9]+)?)s/i', $errorMessage, $matches) === 1) {
            $seconds = (float) $matches[1];
            if ($seconds > 0) {
                return min(max($seconds, 1.0), 12.0);
            }
        }

        return 2.0;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildPrompt(array $context): string
    {
        $requiredSkills = $context['required_skills'];
        $candidateSkills = $context['candidate_skills'];

        if (!is_array($requiredSkills)) {
            $requiredSkills = [];
        }

        if (!is_array($candidateSkills)) {
            $candidateSkills = [];
        }

        return implode("\n", [
            'Evaluate one candidate application against one job offer.',
            'Language: English.',
            'Use this rubric with strict component scores from 0 to 100:',
            '- skill_match: 50% weight',
            '- experience_relevance: 20% weight',
            '- education_fit: 10% weight',
            '- cover_letter_relevance: 10% weight',
            '- cv_relevance: 10% weight',
            '',
            'Return ONLY JSON with this exact shape:',
            '{',
            '  "scores": {',
            '    "skill_match": number,',
            '    "experience_relevance": number,',
            '    "education_fit": number,',
            '    "cover_letter_relevance": number,',
            '    "cv_relevance": number',
            '  },',
            '  "rationale": "1-2 short sentences",',
            '  "matched_skills": ["..."],',
            '  "missing_skills": ["..."]',
            '}',
            '',
            'Job:',
            '- title: ' . (string) $context['job_title'],
            '- description: ' . (string) $context['job_description'],
            '- required_skills: ' . (count($requiredSkills) > 0 ? implode(', ', $requiredSkills) : 'none listed'),
            '',
            'Candidate application:',
            '- candidate_name: ' . (string) $context['candidate_name'],
            '- candidate_skills: ' . (count($candidateSkills) > 0 ? implode(', ', $candidateSkills) : 'none listed'),
            '- experience_years: ' . ($context['experience_years'] === null ? 'not specified' : (string) $context['experience_years']),
            '- education_level: ' . ((string) $context['education_level'] !== '' ? (string) $context['education_level'] : 'not specified'),
            '- cover_letter: ' . (string) $context['cover_letter'],
            '- cv_text: ' . (string) $context['cv_text'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseModelPayload(string $content): array
    {
        $normalized = trim($content);
        $normalized = preg_replace('/^```json\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/```$/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($normalized, '{');
        $end = strrpos($normalized, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new \RuntimeException('Model did not return parseable JSON.');
        }

        $candidateJson = substr($normalized, $start, $end - $start + 1);
        $decoded = json_decode($candidateJson, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Model JSON payload is invalid.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function buildResultFromParsedPayload(array $payload, array $context): array
    {
        $breakdown = [
            'skill_match' => $this->extractScore($payload, 'skill_match'),
            'experience_relevance' => $this->extractScore($payload, 'experience_relevance'),
            'education_fit' => $this->extractScore($payload, 'education_fit'),
            'cover_letter_relevance' => $this->extractScore($payload, 'cover_letter_relevance'),
            'cv_relevance' => $this->extractScore($payload, 'cv_relevance'),
        ];

        $score = $this->calculateWeightedScore($breakdown);
        $rationale = $this->normalizeRationale((string) ($payload['rationale'] ?? 'The candidate was ranked using rubric-based AI evaluation.'));

        return [
            'score' => $score,
            'rationale' => $rationale,
            'breakdown' => $breakdown,
            'matched_skills' => $this->normalizeStringList($payload['matched_skills'] ?? []),
            'missing_skills' => $this->normalizeStringList($payload['missing_skills'] ?? []),
            'inputs_used' => $context['inputs_used'],
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function heuristicFallback(array $context): array
    {
        $requiredSkills = $this->normalizeStringList($context['required_skills'] ?? []);
        $candidateSkills = $this->normalizeStringList($context['candidate_skills'] ?? []);

        $matchedSkills = $this->computeMatchedSkills($requiredSkills, $candidateSkills);
        $missingSkills = array_values(array_diff($requiredSkills, $matchedSkills));

        $requiredCount = count($requiredSkills);
        $matchedCount = count($matchedSkills);

        $skillScore = $requiredCount === 0
            ? 55
            : (int) round(($matchedCount / $requiredCount) * 100);

        $experienceYears = is_numeric($context['experience_years'] ?? null)
            ? (int) $context['experience_years']
            : null;
        $experienceScore = 45;
        if ($experienceYears !== null) {
            if ($experienceYears >= 6) {
                $experienceScore = 90;
            } elseif ($experienceYears >= 3) {
                $experienceScore = 75;
            } elseif ($experienceYears >= 1) {
                $experienceScore = 60;
            }
        }

        $education = mb_strtolower((string) ($context['education_level'] ?? ''));
        $educationScore = 50;
        if ($education !== '') {
            if (preg_match('/phd|doctor|master|engineer/', $education) === 1) {
                $educationScore = 85;
            } elseif (preg_match('/bachelor|licence|degree/', $education) === 1) {
                $educationScore = 70;
            } else {
                $educationScore = 60;
            }
        }

        $coverLength = mb_strlen((string) ($context['cover_letter'] ?? ''));
        $coverScore = $coverLength >= 450 ? 80 : ($coverLength >= 180 ? 65 : ($coverLength >= 80 ? 50 : 35));

        $cvLength = mb_strlen((string) ($context['cv_text'] ?? ''));
        $cvScore = $cvLength >= 1800 ? 80 : ($cvLength >= 800 ? 65 : ($cvLength >= 250 ? 50 : 35));

        $breakdown = [
            'skill_match' => $this->clampScore($skillScore),
            'experience_relevance' => $this->clampScore($experienceScore),
            'education_fit' => $this->clampScore($educationScore),
            'cover_letter_relevance' => $this->clampScore($coverScore),
            'cv_relevance' => $this->clampScore($cvScore),
        ];

        $score = $this->calculateWeightedScore($breakdown);

        $rationale = $matchedCount > 0
            ? sprintf(
                'Heuristic fallback ranked this application at %d/100 based on %d matched required skill(s), profile details, cover letter, and CV evidence.',
                $score,
                $matchedCount
            )
            : sprintf(
                'Heuristic fallback ranked this application at %d/100 based on profile details, cover letter, and CV evidence, with limited direct skill overlap.',
                $score
            );

        return [
            'score' => $score,
            'rationale' => $this->normalizeRationale($rationale),
            'breakdown' => $breakdown,
            'matched_skills' => $matchedSkills,
            'missing_skills' => $missingSkills,
            'inputs_used' => $context['inputs_used'],
        ];
    }

    /**
     * @param array<string, int> $breakdown
     */
    private function calculateWeightedScore(array $breakdown): int
    {
        $score = 0.0;
        foreach (self::WEIGHTS as $key => $weight) {
            $score += ($breakdown[$key] ?? 0) * $weight;
        }

        return $this->clampScore((int) round($score));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractScore(array $payload, string $key): int
    {
        $raw = null;
        if (isset($payload['scores']) && is_array($payload['scores']) && array_key_exists($key, $payload['scores'])) {
            $raw = $payload['scores'][$key];
        } elseif (array_key_exists($key, $payload)) {
            $raw = $payload[$key];
        }

        if (!is_numeric($raw)) {
            return 0;
        }

        return $this->clampScore((int) round((float) $raw));
    }

    private function clampScore(int $value): int
    {
        if ($value < 0) {
            return 0;
        }

        if ($value > 100) {
            return 100;
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $entry) {
            $text = trim((string) $entry);
            if ($text === '') {
                continue;
            }

            $items[] = $text;
        }

        return array_values(array_unique($items));
    }

    /**
     * @param string[] $requiredSkills
     * @param string[] $candidateSkills
     *
     * @return string[]
     */
    private function computeMatchedSkills(array $requiredSkills, array $candidateSkills): array
    {
        $matches = [];
        foreach ($requiredSkills as $requiredSkill) {
            $required = mb_strtolower(trim($requiredSkill));
            if ($required === '') {
                continue;
            }

            foreach ($candidateSkills as $candidateSkill) {
                $candidate = mb_strtolower(trim((string) preg_replace('/\s*\([^)]*\)\s*/', '', $candidateSkill)));
                if ($candidate === '') {
                    continue;
                }

                if (str_contains($candidate, $required) || str_contains($required, $candidate)) {
                    $matches[] = $requiredSkill;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    private function normalizeRationale(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return 'The candidate was ranked using rubric-based AI evaluation.';
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (mb_strlen($text) > 320) {
            $text = trim(mb_substr($text, 0, 317)) . '...';
        }

        return $text;
    }

    /**
     * @return string[]
     */
    private function extractOfferSkills(?Job_offer $offer): array
    {
        if (!$offer) {
            return [];
        }

        $skills = [];
        foreach ($offer->getOffer_skills() as $offerSkill) {
            $skill = trim((string) $offerSkill->getSkill_name());
            if ($skill === '') {
                continue;
            }

            $skills[] = $skill;
        }

        return array_values(array_unique($skills));
    }

    /**
     * @return string[]
     */
    private function extractCandidateSkills(?Candidate $candidate): array
    {
        if (!$candidate) {
            return [];
        }

        return $this->candidateSkillRepository->findSkillSummariesForCandidate($candidate);
    }

    private function resolveCandidateName(?Candidate $candidate): string
    {
        if (!$candidate) {
            return 'Candidate';
        }

        $fullName = trim((string) $candidate->getFirstName() . ' ' . (string) $candidate->getLastName());
        if ($fullName !== '') {
            return $fullName;
        }

        $email = trim((string) $candidate->getEmail());
        if ($email !== '') {
            return $email;
        }

        return 'Candidate';
    }

    private function limit(string $text, int $max): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) <= $max) {
            return $trimmed;
        }

        return trim(mb_substr($trimmed, 0, $max - 3)) . '...';
    }

    private function extractCvText(string $applicationCvPath, string $profileCvPath): string
    {
        $paths = [];
        if ($applicationCvPath !== '') {
            $paths[] = $applicationCvPath;
        }

        if ($profileCvPath !== '' && $profileCvPath !== $applicationCvPath) {
            $paths[] = $profileCvPath;
        }

        foreach ($paths as $path) {
            $text = $this->extractCvTextFromStoredPath($path);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function extractCvTextFromStoredPath(string $storedPath): string
    {
        $normalized = trim(str_replace('\\', '/', $storedPath));
        if ($normalized === '') {
            return '';
        }

        $fileName = basename($normalized);
        if ($fileName === '') {
            return '';
        }

        $candidatePaths = [
            $this->projectDir . '/public/' . ltrim($normalized, '/'),
            $this->projectDir . '/public/uploads/applications/' . $fileName,
            $this->projectDir . '/public/uploads/cvs/' . $fileName,
        ];

        foreach ($candidatePaths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $text = $this->extractReadableTextFromFile($path, $fileName);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function extractReadableTextFromFile(string $absolutePath, string $fileName): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $rawContent = file_get_contents($absolutePath);
        if (!is_string($rawContent) || $rawContent === '') {
            return '';
        }

        if (in_array($extension, ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm'], true)) {
            return $this->normalizeCvText($rawContent);
        }

        if ($extension === 'docx' && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($absolutePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if (is_string($xml) && $xml !== '') {
                    return $this->normalizeCvText(strip_tags($xml));
                }
            }
        }

        if ($extension === 'pdf') {
            return $this->extractPdfText($rawContent);
        }

        return $this->normalizeCvText($rawContent);
    }

    private function extractPdfText(string $rawPdf): string
    {
        $chunks = [];
        if (preg_match_all('/\(([^()]*)\)/s', $rawPdf, $matches) > 0) {
            foreach ($matches[1] as $chunk) {
                $cleanChunk = preg_replace('/\\\\[nrt]/', ' ', (string) $chunk);
                $cleanChunk = preg_replace('/\\\\\d{3}/', ' ', (string) $cleanChunk);
                if (!is_string($cleanChunk)) {
                    continue;
                }

                $chunks[] = $cleanChunk;
            }
        }

        if ($chunks === []) {
            return '';
        }

        return $this->normalizeCvText(implode(' ', $chunks));
    }

    private function normalizeCvText(string $rawText): string
    {
        $normalized = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ' '], $rawText);
        $normalized = strip_tags($normalized);
        $normalized = preg_replace('/[^\PC\n\t]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return '';
        }

        return $this->limit($normalized, 7000);
    }

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Candidate_skillRepository $candidateSkillRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(string:OPENROUTER_API_KEY)%')]
        private readonly string $openRouterApiKey,
        #[Autowire('%env(string:GROQ_API_KEY)%')]
        private readonly string $groqApiKey,
        #[Autowire('%env(string:OPENROUTER_RANKING_MODEL)%')]
        private readonly string $openRouterModel,
        #[Autowire('%env(string:GROQ_RANKING_MODEL)%')]
        private readonly string $groqModel
    ) {
    }
}
