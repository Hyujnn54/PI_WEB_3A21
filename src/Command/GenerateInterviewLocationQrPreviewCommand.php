<?php

namespace App\Command;

use App\Service\Interview\InterviewLocationQrCodeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:interviews:test-location-qr',
    description: 'Generate a QR preview PNG that opens the interview location in Google Maps.',
)]
class GenerateInterviewLocationQrPreviewCommand extends Command
{
    public function __construct(
        private readonly InterviewLocationQrCodeService $locationQrCodeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('location', InputArgument::REQUIRED, 'Readable location label, address, or maps URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $location = trim((string) $input->getArgument('location'));

        $payload = $this->locationQrCodeService->buildOnsiteLocationPayload($location);
        $dataUri = (string) ($payload['qrCodeDataUri'] ?? '');
        $mapsUrl = (string) ($payload['mapsUrl'] ?? '');

        if (!str_starts_with($dataUri, 'data:image/') || !str_contains($dataUri, ';base64,')) {
            $io->error('QR code generation failed.');
            return Command::FAILURE;
        }

        preg_match('#^data:image/([a-zA-Z0-9.+-]+);base64,#', $dataUri, $matches);
        $extension = strtolower((string) ($matches[1] ?? 'png'));
        if ($extension === 'svg+xml') {
            $extension = 'svg';
        }

        [, $base64Payload] = explode(',', $dataUri, 2);
        $binary = base64_decode($base64Payload, true);
        if ($binary === false) {
            $io->error('QR code payload could not be decoded.');
            return Command::FAILURE;
        }

        $targetDir = 'var/qr-preview';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            $io->error('Unable to create QR preview directory.');
            return Command::FAILURE;
        }

        $filename = $targetDir . '/interview-location-' . date('Ymd-His') . '.' . $extension;
        if (file_put_contents($filename, $binary) === false) {
            $io->error('Unable to write QR preview file.');
            return Command::FAILURE;
        }

        $io->success('QR preview generated successfully.');
        $io->writeln('Maps URL: ' . $mapsUrl);
        $io->writeln('QR file: ' . $filename);

        return Command::SUCCESS;
    }
}
