<?php

namespace App\Service\Interview;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class InterviewMapLookupService
{
    private const SEARCH_URL = 'https://nominatim.openstreetmap.org/search';
    private const REVERSE_URL = 'https://nominatim.openstreetmap.org/reverse';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<array{name: string, displayName: string, lat: float, lng: float}>
     */
    public function search(string $query, int $limit = 8): array
    {
        $normalizedQuery = trim($query);
        if ($normalizedQuery === '') {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::SEARCH_URL, [
                'query' => [
                    'format' => 'jsonv2',
                    'limit' => max(1, min($limit, 10)),
                    'addressdetails' => 1,
                    'namedetails' => 1,
                    'q' => $normalizedQuery,
                ],
                'headers' => $this->buildHeaders(),
            ]);

            $payload = $response->toArray(false);

            $results = [];
            foreach ($payload as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $entry = $this->normalizePlace($item);
                if ($entry !== null) {
                    $results[] = $entry;
                }
            }

            return $results;
        } catch (Throwable $exception) {
            $this->logger->warning('Interview map search failed.', [
                'query' => $normalizedQuery,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array{name: string, displayName: string, lat: float, lng: float}|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::REVERSE_URL, [
                'query' => [
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'namedetails' => 1,
                    'lat' => $lat,
                    'lon' => $lng,
                ],
                'headers' => $this->buildHeaders(),
            ]);

            $payload = $response->toArray(false);

            return $this->normalizePlace($payload);
        } catch (Throwable $exception) {
            $this->logger->warning('Interview map reverse lookup failed.', [
                'lat' => $lat,
                'lng' => $lng,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $place
     * @return array{name: string, displayName: string, lat: float, lng: float}|null
     */
    private function normalizePlace(array $place): ?array
    {
        $lat = isset($place['lat']) ? (float) $place['lat'] : null;
        $lng = isset($place['lon']) ? (float) $place['lon'] : null;
        if (!is_float($lat) || !is_float($lng) || !is_finite($lat) || !is_finite($lng)) {
            return null;
        }

        $address = isset($place['address']) && is_array($place['address']) ? $place['address'] : [];
        $namedetails = isset($place['namedetails']) && is_array($place['namedetails']) ? $place['namedetails'] : [];
        $exactDisplayName = $this->sanitizeLabel((string) ($place['display_name'] ?? ''));

        $primary = $this->sanitizeLabel(
            (string) (
                $place['name']
                ?? $namedetails['name']
                ?? $namedetails['name:en']
                ?? $address['amenity']
                ?? $address['building']
                ?? $address['office']
                ?? $address['tourism']
                ?? $address['shop']
                ?? $address['leisure']
                ?? $address['road']
                ?? $exactDisplayName
            )
        );

        $displayName = $exactDisplayName !== ''
            ? $exactDisplayName
            : $this->buildExactFallbackLabel($primary, $address);

        return [
            'name' => $primary,
            'displayName' => $displayName,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * @param array<string, mixed> $address
     */
    private function buildExactFallbackLabel(string $primary, array $address): string
    {
        $parts = [];
        foreach ([
            $primary,
            trim(((string) ($address['house_number'] ?? '')) . ' ' . ((string) ($address['road'] ?? ''))),
            (string) ($address['suburb'] ?? ''),
            (string) ($address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? ''),
            (string) ($address['state'] ?? $address['county'] ?? ''),
            (string) ($address['country'] ?? ''),
        ] as $part) {
            $normalized = $this->sanitizeLabel((string) $part);
            if ($normalized === '') {
                continue;
            }
            if (in_array(mb_strtolower($normalized), array_map('mb_strtolower', $parts), true)) {
                continue;
            }
            $parts[] = $normalized;
        }

        if ($parts === []) {
            return 'Selected location';
        }

        return $this->sanitizeLabel(implode(', ', $parts));
    }

    private function sanitizeLabel(string $value): string
    {
        $sanitized = preg_replace('/[<>\r\n\t]+/u', ' ', trim($value)) ?? '';
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? '';

        return mb_substr(trim($sanitized), 0, 120);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Language' => 'en',
            'User-Agent' => 'TalentBridgeInterviewMapPicker/1.0',
        ];
    }
}
