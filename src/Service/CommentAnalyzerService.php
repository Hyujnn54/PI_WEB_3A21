<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CommentAnalyzerService
{
    private const GOOGLE_LANGUAGE_SCOPE = 'https://www.googleapis.com/auth/cloud-language';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $comment): array
    {
        $normalized = trim($comment);
        if ($normalized === '') {
            return [
                'toxicityScore' => 0.0,
                'spamScore' => 0.0,
                'sentiment' => 'neutral',
                'labels' => [],
                'flagged' => false,
                'autoHidden' => false,
                'provider' => 'heuristic',
            ];
        }

        $googleResult = $this->analyzeWithGoogleLanguage($normalized);
        if (is_array($googleResult)) {
            return $googleResult;
        }

        $lower = mb_strtolower($normalized);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $toxicityScore = $this->computeToxicityScore($lower);
        $spamScore = $this->computeSpamScore($normalized, $lower, $tokens);
        $sentiment = $this->computeSentiment($tokens);
        $labels = $this->buildLabels($lower, $toxicityScore, $spamScore, $sentiment);

        $flagged = $toxicityScore >= 0.75
            || $spamScore >= 0.72
            || in_array('harassment', $labels, true)
            || in_array('hate', $labels, true);

        $autoHidden = $toxicityScore >= 0.9 || $spamScore >= 0.9;

        return [
            'toxicityScore' => round($toxicityScore, 3),
            'spamScore' => round($spamScore, 3),
            'sentiment' => $sentiment,
            'labels' => array_values(array_unique($labels)),
            'flagged' => $flagged,
            'autoHidden' => $autoHidden,
            'provider' => 'heuristic',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analyzeWithGoogleLanguage(string $comment): ?array
    {
        $credentials = $this->loadGoogleCredentials();
        if (!is_array($credentials)) {
            return null;
        }

        $accessToken = $this->fetchGoogleAccessToken($credentials);
        if ($accessToken === '') {
            return null;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        $sentiment = 'neutral';
        $toxicityScore = 0.0;
        $spamScore = 0.0;
        $labels = [];

        try {
            $sentimentResponse = $this->httpClient->request('POST', 'https://language.googleapis.com/v1/documents:analyzeSentiment', [
                'headers' => $headers,
                'json' => [
                    'document' => [
                        'type' => 'PLAIN_TEXT',
                        'content' => $comment,
                    ],
                    'encodingType' => 'UTF8',
                ],
                'timeout' => 20,
            ]);

            if ($sentimentResponse->getStatusCode() < 400) {
                $payload = $sentimentResponse->toArray(false);
                $score = (float) ($payload['documentSentiment']['score'] ?? 0.0);
                if ($score >= 0.2) {
                    $sentiment = 'positive';
                } elseif ($score <= -0.2) {
                    $sentiment = 'negative';
                }
            }
        } catch (\Throwable) {
            // Keep fallback values.
        }

        $moderationSucceeded = false;

        try {
            $moderationResponse = $this->httpClient->request('POST', 'https://language.googleapis.com/v1/documents:moderateText', [
                'headers' => $headers,
                'json' => [
                    'document' => [
                        'type' => 'PLAIN_TEXT',
                        'content' => $comment,
                    ],
                ],
                'timeout' => 20,
            ]);

            if ($moderationResponse->getStatusCode() < 400) {
                $moderationSucceeded = true;
                $payload = $moderationResponse->toArray(false);
                $categories = (array) ($payload['moderationCategories'] ?? []);

                foreach ($categories as $category) {
                    $name = mb_strtolower(trim((string) ($category['name'] ?? '')));
                    $confidence = (float) ($category['confidence'] ?? 0.0);
                    if ($name === '') {
                        continue;
                    }

                    if (
                        str_contains($name, 'toxicity')
                        || str_contains($name, 'insult')
                        || str_contains($name, 'harassment')
                        || str_contains($name, 'profanity')
                        || str_contains($name, 'derogatory')
                        || str_contains($name, 'hate')
                    ) {
                        $toxicityScore = max($toxicityScore, $confidence);
                    }

                    if (str_contains($name, 'spam') || str_contains($name, 'scam') || str_contains($name, 'promotion')) {
                        $spamScore = max($spamScore, $confidence);
                    }

                    if ($confidence >= 0.4) {
                        $labels[] = str_replace(' ', '_', $name);
                    }
                }
            }
        } catch (\Throwable) {
            // Keep fallback values.
        }

        if (!$moderationSucceeded) {
            return null;
        }

        if ($sentiment === 'negative') {
            $labels[] = 'negative';
        } elseif ($sentiment === 'positive') {
            $labels[] = 'positive';
        }

        if ($toxicityScore >= 0.72) {
            $labels[] = 'toxicity';
        }
        if ($spamScore >= 0.72) {
            $labels[] = 'spam';
        }

        $labels = array_values(array_unique($labels));

        $flagged = $toxicityScore >= 0.75
            || $spamScore >= 0.72
            || in_array('hate', $labels, true)
            || in_array('harassment', $labels, true);

        $autoHidden = $toxicityScore >= 0.9 || $spamScore >= 0.9;

        return [
            'toxicityScore' => round($toxicityScore, 3),
            'spamScore' => round($spamScore, 3),
            'sentiment' => $sentiment,
            'labels' => $labels,
            'flagged' => $flagged,
            'autoHidden' => $autoHidden,
            'provider' => 'google-language',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadGoogleCredentials(): ?array
    {
        $credentialPath = $this->resolveGoogleCredentialPath();
        if ($credentialPath === '' || !is_file($credentialPath)) {
            return null;
        }

        try {
            $raw = file_get_contents($credentialPath);
            if ($raw === false) {
                return null;
            }

            $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($json)) {
            return null;
        }

        $requiredFields = ['client_email', 'private_key', 'token_uri'];
        foreach ($requiredFields as $field) {
            if (!isset($json[$field]) || trim((string) $json[$field]) === '') {
                return null;
            }
        }

        return $json;
    }

    private function resolveGoogleCredentialPath(): string
    {
        $candidates = [
            $_ENV['COMMENT_ANALYZER_GOOGLE_CREDENTIALS'] ?? null,
            $_SERVER['COMMENT_ANALYZER_GOOGLE_CREDENTIALS'] ?? null,
            getenv('COMMENT_ANALYZER_GOOGLE_CREDENTIALS') ?: null,
        ];

        foreach ($candidates as $candidate) {
            $path = trim((string) $candidate);
            if ($path !== '') {
                return str_replace('\\', '/', $path);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function fetchGoogleAccessToken(array $credentials): string
    {
        if (!function_exists('openssl_sign')) {
            return '';
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => (string) $credentials['client_email'],
            'scope' => self::GOOGLE_LANGUAGE_SCOPE,
            'aud' => (string) $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwtUnsigned = $this->base64UrlEncode((string) json_encode($header, JSON_THROW_ON_ERROR))
            . '.'
            . $this->base64UrlEncode((string) json_encode($claims, JSON_THROW_ON_ERROR));

        $signature = '';
        $privateKey = (string) $credentials['private_key'];

        try {
            $signed = openssl_sign($jwtUnsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            if ($signed !== true) {
                return '';
            }
        } catch (\Throwable) {
            return '';
        }

        $assertion = $jwtUnsigned . '.' . $this->base64UrlEncode($signature);

        try {
            $tokenResponse = $this->httpClient->request('POST', (string) $credentials['token_uri'], [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ],
                'timeout' => 20,
            ]);

            if ($tokenResponse->getStatusCode() >= 400) {
                return '';
            }

            $payload = $tokenResponse->toArray(false);
            return trim((string) ($payload['access_token'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function computeToxicityScore(string $lower): float
    {
        $weights = [
            'idiot' => 0.18,
            'stupid' => 0.2,
            'dumb' => 0.2,
            'trash' => 0.16,
            'useless' => 0.16,
            'hate' => 0.22,
            'racist' => 0.3,
            'sexist' => 0.3,
            'kill' => 0.35,
            'die' => 0.3,
            'moron' => 0.2,
            'scam' => 0.2,
            'fraud' => 0.24,
            'loser' => 0.14,
        ];

        $score = 0.0;
        foreach ($weights as $term => $weight) {
            if (str_contains($lower, $term)) {
                $score += $weight;
            }
        }

        $exclamationCount = preg_match_all('/!/', $lower);
        if ($exclamationCount !== false && $exclamationCount >= 3) {
            $score += 0.06;
        }

        $uppercaseMatches = preg_match_all('/\b[A-Z]{4,}\b/', $lower);
        if ($uppercaseMatches !== false && $uppercaseMatches > 0) {
            $score += 0.05;
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * @param array<int, string> $tokens
     */
    private function computeSpamScore(string $raw, string $lower, array $tokens): float
    {
        $score = 0.0;

        $linkCount = preg_match_all('/https?:\/\/|www\./i', $raw);
        if ($linkCount !== false) {
            if ($linkCount >= 2) {
                $score += 0.5;
            } elseif ($linkCount === 1) {
                $score += 0.2;
            }
        }

        $promoTerms = [
            'buy now',
            'click here',
            'limited offer',
            'free money',
            'easy cash',
            'promo code',
            'subscribe',
            'telegram',
            'whatsapp',
        ];
        foreach ($promoTerms as $term) {
            if (str_contains($lower, $term)) {
                $score += 0.12;
            }
        }

        if (preg_match('/(.)\1{5,}/u', $lower)) {
            $score += 0.18;
        }

        if (count($tokens) > 0) {
            $tokenCounts = array_count_values($tokens);
            $mostFrequent = 0;
            foreach ($tokenCounts as $count) {
                if ($count > $mostFrequent) {
                    $mostFrequent = $count;
                }
            }

            if ($mostFrequent >= 4) {
                $score += 0.18;
            }
        }

        $lettersOnly = preg_replace('/[^A-Za-z]/', '', $raw) ?? '';
        if ($lettersOnly !== '') {
            $upperLetters = preg_replace('/[^A-Z]/', '', $lettersOnly) ?? '';
            $ratio = strlen($upperLetters) / strlen($lettersOnly);
            if ($ratio >= 0.55) {
                $score += 0.16;
            }
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * @param array<int, string> $tokens
     */
    private function computeSentiment(array $tokens): string
    {
        $positiveTerms = [
            'good', 'great', 'excellent', 'nice', 'helpful', 'clear', 'professional', 'thanks', 'amazing',
        ];
        $negativeTerms = [
            'bad', 'awful', 'terrible', 'hate', 'useless', 'worse', 'worst', 'scam', 'fraud',
        ];

        $positive = 0;
        $negative = 0;

        foreach ($tokens as $token) {
            if (in_array($token, $positiveTerms, true)) {
                $positive++;
            }
            if (in_array($token, $negativeTerms, true)) {
                $negative++;
            }
        }

        if ($positive >= $negative + 2) {
            return 'positive';
        }

        if ($negative >= $positive + 2) {
            return 'negative';
        }

        return 'neutral';
    }

    /**
     * @return array<int, string>
     */
    private function buildLabels(string $lower, float $toxicityScore, float $spamScore, string $sentiment): array
    {
        $labels = [];

        if ($toxicityScore >= 0.75) {
            $labels[] = 'toxicity';
        }

        if ($spamScore >= 0.72) {
            $labels[] = 'spam';
        }

        if (str_contains($lower, 'hate') || str_contains($lower, 'racist') || str_contains($lower, 'sexist')) {
            $labels[] = 'hate';
        }

        if (str_contains($lower, 'idiot') || str_contains($lower, 'moron') || str_contains($lower, 'stupid')) {
            $labels[] = 'harassment';
        }

        if (preg_match('/https?:\/\/|www\./i', $lower)) {
            $labels[] = 'contains_links';
        }

        if ($sentiment === 'negative') {
            $labels[] = 'negative';
        } elseif ($sentiment === 'positive') {
            $labels[] = 'positive';
        }

        return $labels;
    }
}
