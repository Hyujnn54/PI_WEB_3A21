<?php

namespace App\Service;

/**
 * GeolocationService
 *
 * Central service for all geographic operations in the recruitment platform:
 *  - converting a city name into latitude/longitude via Nominatim (OpenStreetMap)
 *  - computing the straight-line distance between two coordinates (Haversine formula)
 *
 * This service wraps the existing JobOfferLocationGeocoder so that the same
 * Nominatim HTTP client is reused across the application.
 */
class GeolocationService
{
    /** Earth's mean radius in kilometres (used for the Haversine formula). */
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        /**
         * Injected by Symfony's autowiring.
         * JobOfferLocationGeocoder already holds the configured Nominatim provider.
         */
        private readonly JobOfferLocationGeocoder $locationGeocoder
    ) {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Geocode a city / address string and return its coordinates.
     *
     * @param string $city Any human-readable location string (e.g. "Tunis", "Sousse, Tunisia").
     *
     * @return array{lat: float, lng: float}
     *
     * @throws \RuntimeException When the city cannot be geocoded (unknown place, network error, etc.).
     */
    public function geocode(string $city): array
    {
        $city = trim($city);

        if ($city === '') {
            throw new \RuntimeException('Cannot geocode an empty city name.');
        }

        // Delegate to JobOfferLocationGeocoder which already handles Nominatim calls.
        $result = $this->locationGeocoder->geocodeLocation($city);

        if ($result === null) {
            throw new \RuntimeException(
                sprintf('Geocoding failed: city "%s" could not be found.', $city)
            );
        }

        return [
            'lat' => (float) ($result['latitude'] ?? 0.0),
            'lng' => (float) ($result['longitude'] ?? 0.0),
        ];
    }

    /**
     * Calculate the great-circle distance between two geographic points
     * using the Haversine formula.
     *
     * @param float $lat1 Latitude of point 1 in decimal degrees.
     * @param float $lng1 Longitude of point 1 in decimal degrees.
     * @param float $lat2 Latitude of point 2 in decimal degrees.
     * @param float $lng2 Longitude of point 2 in decimal degrees.
     *
     * @return float Distance in kilometres (rounded to 2 decimal places).
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // self::EARTH_RADIUS_KM is defined as a class constant above.

        // Convert degrees → radians.
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        // Haversine formula.
        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));

        return round(self::EARTH_RADIUS_KM * $c, 2);
    }

    // -------------------------------------------------------------------------
    // Convenience helpers
    // -------------------------------------------------------------------------

    /**
     * Try to geocode a city and return coordinates, or null on any failure.
     * Use this in contexts where a missing city is acceptable (no exception thrown).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function tryGeocode(string $city): ?array
    {
        try {
            return $this->geocode($city);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build a structured result used by the job-offers list:
     * [ 'offer' => array (raw DB row), 'distance' => float|null ]
     *
     * @param array<string, mixed>      $offer           Raw DB row for a job offer.
     * @param float|null                $candidateLat    Candidate's latitude (null if unknown).
     * @param float|null                $candidateLng    Candidate's longitude (null if unknown).
     *
     * @return array{offer: array<string, mixed>, distance: float|null}
     */
    public function buildOfferWithDistance(
        array $offer,
        ?float $candidateLat,
        ?float $candidateLng
    ): array {
        $offerLat = isset($offer['latitude']) ? (float) $offer['latitude'] : null;
        $offerLng = isset($offer['longitude']) ? (float) $offer['longitude'] : null;

        $distance = null;

        if (
            $candidateLat !== null
            && $candidateLng !== null
            && $offerLat !== null
            && $offerLng !== null
            // Skip if both are (0, 0) — the default "geocoding failed" sentinel.
            && !($offerLat === 0.0 && $offerLng === 0.0)
            && !($candidateLat === 0.0 && $candidateLng === 0.0)
        ) {
            $distance = $this->calculateDistance($candidateLat, $candidateLng, $offerLat, $offerLng);
        }

        return [
            'offer'    => $offer,
            'distance' => $distance,
        ];
    }
}
