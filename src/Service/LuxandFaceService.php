<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LuxandFaceService
{
    public function __construct(
        #[Autowire('%env(string:LUXAND_API_TOKEN)%')] private readonly string $apiToken,
        #[Autowire('%env(string:LUXAND_BASE_URL)%')] private readonly string $baseUrl,
        #[Autowire('%env(string:LUXAND_COLLECTION)%')] private readonly string $collection,
        #[Autowire('%env(float:LUXAND_FACE_THRESHOLD)%')] private readonly float $faceThreshold,
        #[Autowire('%env(float:LUXAND_LIVENESS_THRESHOLD)%')] private readonly float $livenessThreshold,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function faceThreshold(): float
    {
        return $this->faceThreshold;
    }

    public function livenessThreshold(): float
    {
        return $this->livenessThreshold;
    }

    public function addPerson(string $name, UploadedFile $photo): string
    {
        $fields = [
            'name' => $name,
            'store' => '1',
            'collections' => $this->collection,
        ];

        $payload = [];
        try {
            $payload = $this->sendMultipart('/v2/person', $fields, 'photos', $photo);
        } catch (\Throwable) {
            try {
                $payload = $this->sendMultipart('/v2/person', $fields, 'photo', $photo);
            } catch (\Throwable) {
                $payload = $this->sendMultipart('/photo', [
                    'name' => $name,
                    'gallery' => $this->collection,
                    'collections' => $this->collection,
                ], 'photo', $photo);
            }
        }

        $uuid = (string) ($payload['uuid'] ?? '');
        if ($uuid === '') {
            $uuid = (string) ($payload['person_uuid'] ?? '');
        }

        if ($uuid === '') {
            $match = $this->searchBestMatch($photo);
            $uuid = (string) ($match['uuid'] ?? '');
        }

        if ($uuid === '') {
            $payloadText = json_encode($payload, JSON_UNESCAPED_SLASHES);
            throw new \RuntimeException('Luxand response does not include person uuid. Raw response: ' . ($payloadText ?: '{}'));
        }

        return $uuid;
    }

    public function addFace(string $personUuid, UploadedFile $photo): void
    {
        $this->sendMultipart('/person/' . rawurlencode($personUuid), [
            'store' => '1',
            'collections' => $this->collection,
        ], 'photo', $photo);
    }

    /**
     * @return array{uuid: string, probability: float}|null
     */
    public function searchBestMatch(UploadedFile $photo): ?array
    {
        $payload = $this->sendMultipart('/photo/search/v2', [
            'collections' => $this->collection,
        ], 'photo', $photo);

        if ($payload === [] || !isset($payload[0]) || !is_array($payload[0])) {
            return null;
        }

        $first = $payload[0];
        $uuid = (string) ($first['uuid'] ?? '');
        if ($uuid === '') {
            return null;
        }

        return [
            'uuid' => $uuid,
            'probability' => (float) ($first['probability'] ?? 0),
        ];
    }

    /**
     * @return array{isReal: bool, score: float, rectangle: array{left: int, top: int, right: int, bottom: int}}
     */
    public function checkLiveness(UploadedFile $photo): array
    {
        $payload = $this->sendMultipart('/photo/liveness', [], 'photo', $photo);

        return [
            'isReal' => strtolower((string) ($payload['result'] ?? '')) === 'real',
            'score' => (float) ($payload['score'] ?? 0),
            'rectangle' => [
                'left' => (int) (($payload['rectangle']['left'] ?? 0)),
                'top' => (int) (($payload['rectangle']['top'] ?? 0)),
                'right' => (int) (($payload['rectangle']['right'] ?? 0)),
                'bottom' => (int) (($payload['rectangle']['bottom'] ?? 0)),
            ],
        ];
    }

    /**
     * @param array<string, string> $fields
     * @return array<array-key, mixed>
     */
    private function sendMultipart(string $path, array $fields, string $fileField, UploadedFile $photo): array
    {
        if ($this->apiToken === '') {
            throw new \RuntimeException('Missing LUXAND_API_TOKEN in environment configuration.');
        }

        $formFields = $fields;
        $formFields[$fileField] = DataPart::fromPath(
            $photo->getPathname(),
            $photo->getClientOriginalName() ?: 'photo.jpg',
            $photo->getMimeType() ?: 'image/jpeg'
        );

        $multipart = new FormDataPart($formFields);
        $body = $multipart->bodyToString();
        $headers = $multipart->getPreparedHeaders()->toArray();
        $headers['token'] = $this->apiToken;
        $headers['Accept-Encoding'] = 'identity';
        $headers['Content-Length'] = (string) strlen($body);

        $response = $this->client()->request('POST', rtrim($this->baseUrl, '/') . $path, [
            'headers' => $headers,
            'body' => $body,
            'http_version' => '1.1',
            'timeout' => 20,
        ]);

        $status = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($status >= 400) {
            throw new \RuntimeException('Luxand request failed: ' . $content);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function client(): HttpClientInterface
    {
        return $this->httpClient ?? HttpClient::create();
    }
}
