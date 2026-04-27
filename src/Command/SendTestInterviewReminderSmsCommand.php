<?php

namespace App\Command;

use App\Service\Interview\ReminderMessageBuilder;
use App\Service\Interview\SmsMobileApiSender;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:interviews:test-reminder-sms',
    description: 'Send a test interview reminder SMS using the SMS Mobile API integration.',
)]
class SendTestInterviewReminderSmsCommand extends Command
{
    public function __construct(
        private readonly SmsMobileApiSender $smsSender,
        private readonly ReminderMessageBuilder $messageBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'Recipient phone number')
            ->addOption('offer', null, InputOption::VALUE_OPTIONAL, 'Offer title', 'Senior Full Stack Developer')
            ->addOption('scheduled-at', null, InputOption::VALUE_OPTIONAL, 'Date/time label', (new DateTimeImmutable('+24 hours'))->format('d M Y H:i'))
            ->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'Interview mode: online or onsite', 'onsite')
            ->addOption('meeting-link', null, InputOption::VALUE_OPTIONAL, 'Meeting link for online mode', 'https://meet.jit.si/TalentBridgeDemoRoom')
            ->addOption('location', null, InputOption::VALUE_OPTIONAL, 'Location for onsite mode', 'Google HQ, Mountain View');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $phone = trim((string) $input->getArgument('phone'));
        $offerTitle = trim((string) $input->getOption('offer'));
        $scheduledAt = trim((string) $input->getOption('scheduled-at'));
        $mode = strtolower(trim((string) $input->getOption('mode')));
        $meetingLink = trim((string) $input->getOption('meeting-link'));
        $location = trim((string) $input->getOption('location'));

        if ($mode !== 'online' && $mode !== 'onsite') {
            $io->error('Mode must be either online or onsite.');
            return Command::INVALID;
        }

        if (!$this->smsSender->isEnabled()) {
            $io->error('SMS sender is not enabled. Configure SMS_MOBILE_API_ENDPOINT and SMS_MOBILE_API_KEY first.');
            return Command::FAILURE;
        }

        $placeLabel = $mode === 'online'
            ? ($meetingLink !== '' ? $meetingLink : 'Meeting link not provided')
            : ($location !== '' ? $location : 'Location not provided');

        $smsText = $this->messageBuilder->buildSmsText(
            'candidate',
            'Candidate',
            $offerTitle !== '' ? $offerTitle : 'Interview',
            $scheduledAt !== '' ? $scheduledAt : (new DateTimeImmutable('+24 hours'))->format('d M Y H:i'),
            strtoupper($mode),
            $placeLabel
        );

        $ok = $this->smsSender->send($phone, $smsText);
        if (!$ok) {
            $io->error('SMS send failed. Check var/log/dev.log for API response details.');
            return Command::FAILURE;
        }

        $io->success('Test reminder SMS sent successfully to ' . $phone . '.');
        return Command::SUCCESS;
    }
}
