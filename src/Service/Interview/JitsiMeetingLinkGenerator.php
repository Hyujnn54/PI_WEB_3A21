<?php

namespace App\Service\Interview;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

class JitsiMeetingLinkGenerator
{
    public function __construct(
        #[Autowire('%env(string:JITSI_BASE_URL)%')]
        private readonly string $configuredBaseUrl,
    ) {
    }

    public function generate(?string $applicationId = null, ?string $interviewId = null): string
    {
        $baseUrl = rtrim(trim($this->configuredBaseUrl), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://meet.jit.si';
        }

        $parts = ['talentbridge', 'interview'];

        $safeApplicationId = $this->sanitizeToken($applicationId);
        if ($safeApplicationId !== '') {
            $parts[] = 'app' . $safeApplicationId;
        }

        $safeInterviewId = $this->sanitizeToken($interviewId);
        if ($safeInterviewId !== '') {
            $parts[] = 'int' . $safeInterviewId;
        }

        $parts[] = $this->generateSuffix();

        return $baseUrl . '/' . implode('-', $parts);
    }

    private function sanitizeToken(?string $value): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? '';
    }

    private function generateSuffix(): string
    {
        try {
            return strtolower(bin2hex(random_bytes(6)));
        } catch (Throwable) {
            return substr(sha1(uniqid((string) mt_rand(), true)), 0, 12);
        }
    }
}
