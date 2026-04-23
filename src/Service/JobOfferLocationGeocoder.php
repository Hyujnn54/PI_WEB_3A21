<?php

namespace App\Service;

use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;

class JobOfferLocationGeocoder
{
    public function __construct(private readonly Provider $nominatimGeocoder)
    {
    }

    /**
     * @return array<string, float|string>|null
     */
    public function geocodeLocation(string $location): ?array
    {
        $query = trim($location);
        if ($query === '') {
            return null;
        }

        try {
            $result = $this->nominatimGeocoder->geocodeQuery(GeocodeQuery::create($query)->withLimit(1));
            if ($result->isEmpty()) {
                return null;
            }

            $address = $result->first();
            $coordinates = $address->getCoordinates();
            if ($coordinates === null) {
                return null;
            }

            $resolvedLocation = $this->resolveAddressLabel($address, $query);
            if ($resolvedLocation === '') {
                $resolvedLocation = $this->normalizeLabel($query);
            }

            return [
                'location' => $resolvedLocation,
                'latitude' => (float) $coordinates->getLatitude(),
                'longitude' => (float) $coordinates->getLongitude(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, float|string|null>>
     */
    public function suggestLocations(string $term, int $limit = 5): array
    {
        $query = trim($term);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $safeLimit = max(1, min(8, $limit));

        try {
            $result = $this->nominatimGeocoder->geocodeQuery(GeocodeQuery::create($query)->withLimit($safeLimit));
        } catch (\Throwable) {
            return [];
        }

        $suggestions = [];
        $seen = [];

        foreach ($result as $address) {
            $label = $this->resolveAddressLabel($address, $query);
            if ($label === '' || isset($seen[$label])) {
                continue;
            }

            $coordinates = $address->getCoordinates();
            $suggestions[] = [
                'label' => $label,
                'latitude' => $coordinates ? (float) $coordinates->getLatitude() : null,
                'longitude' => $coordinates ? (float) $coordinates->getLongitude() : null,
            ];
            $seen[$label] = true;

            if (count($suggestions) >= $safeLimit) {
                break;
            }
        }

        return $suggestions;
    }

    private function normalizeLabel(string $value): string
    {
        $label = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($label === '') {
            return '';
        }

        if (mb_strlen($label) > 120) {
            $label = trim(mb_substr($label, 0, 120));
        }

        return $label;
    }

    /**
     * BazingaGeocoder/Nominatim address models can differ slightly between versions,
     * so we resolve a human-readable label defensively.
     */
    private function resolveAddressLabel(object $address, string $fallback): string
    {
        foreach (['getFormattedAddress', 'getDisplayName'] as $method) {
            if (method_exists($address, $method)) {
                $label = trim((string) $address->{$method}());
                if ($label !== '') {
                    return $this->normalizeLabel($label);
                }
            }
        }

        if (method_exists($address, '__toString')) {
            try {
                $label = trim((string) $address);
                if ($label !== '') {
                    return $this->normalizeLabel($label);
                }
            } catch (\Throwable) {
                // Fall back to the original query below.
            }
        }

        return $this->normalizeLabel($fallback);
    }
}
