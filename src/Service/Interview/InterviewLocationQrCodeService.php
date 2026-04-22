<?php

namespace App\Service\Interview;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Psr\Log\LoggerInterface;
use Throwable;

class InterviewLocationQrCodeService
{
    private const MAPS_BASE_URL = 'https://www.google.com/maps/search/?api=1&query=';
    private const EMAIL_QR_IMAGE_BASE_URL = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&format=png&data=';
    private const COORDINATE_PATTERN = '/\((-?\d{1,3}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)\)\s*$/';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function buildOnsiteLocationPayload(?string $location): array
    {
        $normalizedLocation = trim((string) $location);
        if ($normalizedLocation === '') {
            return [
                'mapsUrl' => '',
                'qrCodeDataUri' => '',
                'qrCodeImageUrl' => '',
            ];
        }

        $mapsUrl = $this->buildMapsUrl($normalizedLocation);
        $qrCodeImageUrl = $this->buildEmailQrImageUrl($mapsUrl);

        try {
            $result = (new Builder())->build(
                writer: new SvgWriter(),
                data: $mapsUrl,
                size: 280,
                margin: 10,
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            );

            return [
                'mapsUrl' => $mapsUrl,
                'qrCodeDataUri' => $result->getDataUri(),
                'qrCodeImageUrl' => $qrCodeImageUrl,
            ];
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to generate interview location QR code.', [
                'location' => $normalizedLocation,
                'message' => $exception->getMessage(),
            ]);

            return [
                'mapsUrl' => $mapsUrl,
                'qrCodeDataUri' => '',
                'qrCodeImageUrl' => $qrCodeImageUrl,
            ];
        }
    }

    private function buildMapsUrl(string $normalizedLocation): string
    {
        if ((bool) filter_var($normalizedLocation, FILTER_VALIDATE_URL)) {
            return $normalizedLocation;
        }

        if (preg_match(self::COORDINATE_PATTERN, $normalizedLocation, $matches) === 1) {
            $lat = (float) $matches[1];
            $lng = (float) $matches[2];
            if ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0) {
                return self::MAPS_BASE_URL . rawurlencode(sprintf('%.6f,%.6f', $lat, $lng));
            }
        }

        return self::MAPS_BASE_URL . rawurlencode($normalizedLocation);
    }

    private function buildEmailQrImageUrl(string $mapsUrl): string
    {
        if (!(bool) filter_var($mapsUrl, FILTER_VALIDATE_URL)) {
            return '';
        }

        return self::EMAIL_QR_IMAGE_BASE_URL . rawurlencode($mapsUrl);
    }
}
