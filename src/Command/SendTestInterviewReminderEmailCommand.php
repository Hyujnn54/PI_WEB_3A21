<?php

namespace App\Command;

use App\Service\Interview\BrevoEmailSender;
use App\Service\Interview\InterviewLocationQrCodeService;
use App\Service\Interview\ReminderMessageBuilder;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

#[AsCommand(
    name: 'app:interviews:test-reminder-email',
    description: 'Send a test interview reminder email using the Brevo integration.',
)]
class SendTestInterviewReminderEmailCommand extends Command
{
    public function __construct(
        private readonly BrevoEmailSender $emailSender,
        private readonly MailerInterface $mailer,
        private readonly ReminderMessageBuilder $messageBuilder,
        private readonly InterviewLocationQrCodeService $locationQrCodeService,
        #[Autowire('%env(string:MAILER_FROM_ADDRESS)%')]
        private readonly string $mailerFromAddress,
        #[Autowire('%env(string:MAILER_FROM_NAME)%')]
        private readonly string $mailerFromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('toEmail', InputArgument::REQUIRED, 'Recipient email address')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Recipient display name', 'Candidate')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Role label in the template', 'Candidate')
            ->addOption('offer', null, InputOption::VALUE_OPTIONAL, 'Offer title', 'Senior Full Stack Developer')
            ->addOption('scheduled-at', null, InputOption::VALUE_OPTIONAL, 'Date/time label', (new DateTimeImmutable('+24 hours'))->format('d M Y H:i'))
            ->addOption('duration', null, InputOption::VALUE_OPTIONAL, 'Duration minutes', '45')
            ->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'Interview mode: online or onsite', 'online')
            ->addOption('meeting-link', null, InputOption::VALUE_OPTIONAL, 'Meeting link for online mode', 'https://meet.jit.si/TalentBridgeDemoRoom')
            ->addOption('location', null, InputOption::VALUE_OPTIONAL, 'Location for onsite mode', 'Talent Bridge HQ, Meeting Room A')
            ->addOption('notes', null, InputOption::VALUE_OPTIONAL, 'Optional notes', 'Please join 5 minutes early and prepare your ID.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $toEmail = trim((string) $input->getArgument('toEmail'));
        $recipientName = trim((string) $input->getOption('name'));
        $roleLabel = trim((string) $input->getOption('role'));
        $offerTitle = trim((string) $input->getOption('offer'));
        $scheduledAt = trim((string) $input->getOption('scheduled-at'));
        $durationMinutes = (int) $input->getOption('duration');
        $mode = strtolower(trim((string) $input->getOption('mode')));
        $meetingLink = trim((string) $input->getOption('meeting-link'));
        $location = trim((string) $input->getOption('location'));
        $notes = trim((string) $input->getOption('notes'));

        if ($mode !== 'online' && $mode !== 'onsite') {
            $io->error('Mode must be either online or onsite.');
            return Command::INVALID;
        }

        if ($durationMinutes <= 0) {
            $io->error('Duration must be greater than zero.');
            return Command::INVALID;
        }

        if ($recipientName === '') {
            $recipientName = 'Candidate';
        }

        if ($roleLabel === '') {
            $roleLabel = 'Candidate';
        }

        if ($offerTitle === '') {
            $offerTitle = 'Interview';
        }

        $modeLabel = strtoupper($mode);
        $placeLabel = $mode === 'online'
            ? ($meetingLink !== '' ? $meetingLink : 'Meeting link not provided')
            : ($location !== '' ? $location : 'Location not provided');
        $mapsUrl = '';
        $locationQrCodeImageUrl = '';

        if ($mode === 'onsite') {
            $locationPayload = $this->locationQrCodeService->buildOnsiteLocationPayload($placeLabel);
            $mapsUrl = (string) ($locationPayload['mapsUrl'] ?? '');
            $locationQrCodeImageUrl = (string) ($locationPayload['qrCodeImageUrl'] ?? '');
        }

        $subject = $this->messageBuilder->buildSubject($offerTitle);
        $textBody = $this->messageBuilder->buildEmailText(
            $recipientName,
            $roleLabel,
            $offerTitle,
            $scheduledAt,
            $durationMinutes,
            $modeLabel,
            $placeLabel,
            $notes,
            $mapsUrl
        );
        $htmlBody = $this->messageBuilder->buildEmailHtml(
            $recipientName,
            $roleLabel,
            $offerTitle,
            $scheduledAt,
            $durationMinutes,
            $modeLabel,
            $placeLabel,
            $notes,
            $mapsUrl,
            $locationQrCodeImageUrl
        );

        if ($this->emailSender->isEnabled()) {
            $ok = $this->emailSender->send($toEmail, $recipientName, $subject, $textBody, $htmlBody);
            if ($ok) {
                $io->success('Test reminder email sent successfully to ' . $toEmail . ' via Brevo.');
                return Command::SUCCESS;
            }

            $io->warning('Brevo email send failed. Falling back to Symfony Mailer.');
        } else {
            $io->warning('Brevo sender is not enabled. Falling back to Symfony Mailer.');
        }

        if (!$this->sendWithSymfonyMailer($toEmail, $recipientName, $subject, $textBody, $htmlBody)) {
            $io->error('Test reminder email failed. Check BREVO_API_KEY or MAILER_DSN and var/log/dev.log for details.');
            return Command::FAILURE;
        }

        $io->success('Test reminder email sent successfully to ' . $toEmail . ' via Symfony Mailer.');
        return Command::SUCCESS;
    }

    private function sendWithSymfonyMailer(string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): bool
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL) || !filter_var($this->mailerFromAddress, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $email = (new Email())
                ->from(new Address($this->mailerFromAddress, trim($this->mailerFromName) !== '' ? trim($this->mailerFromName) : 'Talent Bridge'))
                ->to(new Address($toEmail, $toName))
                ->subject($subject)
                ->text($textBody)
                ->html($htmlBody);

            $this->mailer->send($email);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
