<?php

namespace App\Command;

use App\Service\Interview\InterviewReminderDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:interviews:send-reminders',
    description: 'Send interview reminders 24 hours before via Brevo and SMS Mobile API.',
)]
class SendInterviewRemindersCommand extends Command
{
    public function __construct(private readonly InterviewReminderDispatcher $dispatcher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Interview Reminder Dispatch');

        $stats = $this->dispatcher->dispatchDueReminders();

        $io->definitionList(
            ['Due interviews in 24h window' => (string) $stats['due']],
            ['Processed interviews' => (string) $stats['processed']],
            ['Skipped interviews' => (string) $stats['skipped']],
            ['Emails sent' => (string) $stats['emails_sent']],
            ['SMS sent' => (string) $stats['sms_sent']],
        );

        if ((int) $stats['processed'] === 0) {
            $io->success('No interview reminders were dispatched.');
            return Command::SUCCESS;
        }

        $io->success(sprintf('Interview reminders processed successfully: %d.', (int) $stats['processed']));

        return Command::SUCCESS;
    }
}
