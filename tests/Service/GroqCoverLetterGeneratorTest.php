<?php

namespace App\Tests\Service;

use App\Service\JobApplication\GroqCoverLetterGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GroqCoverLetterGeneratorTest extends TestCase
{
    public function testGenerateHandlesMalformedUtf8InPromptContext(): void
    {
        $requestOptions = [];
        $client = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requestOptions): MockResponse {
                $requestOptions = $options;

                return new MockResponse(
                    '{"choices":[{"message":{"content":"Dear hiring team, I am excited to apply for this role because my Symfony experience and communication skills match your needs."}}]}',
                    ['response_headers' => ['content-type: application/json']]
                );
            }
        );
        $generator = new GroqCoverLetterGenerator($client, 'test-api-key');

        $coverLetter = $generator->generate([
            'candidate_name' => "Aziz\xC3\x28 Gharbi",
            'candidate_email' => 'candidate@example.com',
            'candidate_phone' => '+21655123456',
            'candidate_location' => "Tunis\xE9",
            'education_level' => 'Engineering',
            'experience_years' => '3 years',
            'skills' => ["Symfony\xB1", 'PHP'],
            'offer_title' => 'Backend Developer',
            'offer_location' => 'Tunis',
            'offer_contract' => 'CDI',
            'cv_text' => "Worked on APIs and PDF exports.\xC3\x28",
        ]);

        $this->assertSame(
            'Dear hiring team, I am excited to apply for this role because my Symfony experience and communication skills match your needs.',
            $coverLetter
        );
        $this->assertArrayHasKey('body', $requestOptions);
        $payload = json_decode((string) $requestOptions['body'], true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('messages', $payload);
        $this->assertTrue(mb_check_encoding($payload['messages'][1]['content'], 'UTF-8'));
    }
}
