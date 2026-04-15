<?php

namespace App\Service\Interview;

use App\Entity\Interview;
use App\Entity\Users;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Throwable;

class InterviewReminderDispatcher
{
    private const WINDOW_START_HOURS = 23;
    private const WINDOW_END_HOURS = 25;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly BrevoEmailSender $emailSender,
        private readonly SmsMobileApiSender $smsSender,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatchDueReminders(): array
    {
        $stats = [
            'due' => 0,
            'processed' => 0,
            'skipped' => 0,
            'emails_sent' => 0,
            'sms_sent' => 0,
        ];

        $transportEnabled = $this->emailSender->isEnabled() || $this->smsSender->isEnabled();
        if (!$transportEnabled) {
            $this->logger->warning('Interview reminders are disabled because Brevo and SMS Mobile API are not configured.');
            return $stats;
        }

        $now = new \DateTimeImmutable();
        $windowStart = $now->modify('+' . self::WINDOW_START_HOURS . ' hours');
        $windowEnd = $now->modify('+' . self::WINDOW_END_HOURS . ' hours');

        $interviews = $this->doctrine
            ->getRepository(Interview::class)
            ->createQueryBuilder('i')
            ->andWhere('i.reminder_sent = :sent')
            ->andWhere('i.scheduled_at BETWEEN :windowStart AND :windowEnd')
            ->andWhere('LOWER(i.status) = :status')
            ->setParameter('sent', false)
            ->setParameter('windowStart', $windowStart)
            ->setParameter('windowEnd', $windowEnd)
            ->setParameter('status', 'scheduled')
            ->getQuery()
            ->getResult();

        foreach ($interviews as $row) {
            if (!$row instanceof Interview) {
                continue;
            }

            ++$stats['due'];

            try {
                $context = $this->buildReminderContext($row);
                if (!$context['ok']) {
                    ++$stats['skipped'];
                    continue;
                }

                $subject = sprintf('Interview reminder: %s in 24 hours', $context['offerTitle']);

                foreach ($context['recipients'] as $recipient) {
                    $recipientName = $recipient['name'];
                    $email = $recipient['email'];
                    $phone = $recipient['phone'];
                    $roleLabel = ucfirst($recipient['role']);

                    $emailBody = $this->buildEmailBody(
                        $recipientName,
                        $roleLabel,
                        $context['offerTitle'],
                        $context['scheduledAt'],
                        $context['durationMinutes'],
                        $context['modeLabel'],
                        $context['placeLabel'],
                        $context['notes']
                    );

                    if ($email !== '' && $this->emailSender->send($email, $recipientName, $subject, $emailBody)) {
                        ++$stats['emails_sent'];
                    }

                    $smsText = $this->buildSmsText(
                        $context['offerTitle'],
                        $context['scheduledAt'],
                        $context['modeLabel'],
                        $context['placeLabel']
                    );
                    if ($phone !== '' && $this->smsSender->send($phone, $smsText)) {
                        ++$stats['sms_sent'];
                    }
                }

                // Reuse existing reminder_sent column to guarantee reminders are never dispatched twice.
                $row->setReminder_sent(true);
                ++$stats['processed'];
            } catch (Throwable $exception) {
                ++$stats['skipped'];
                $this->logger->error('Failed to dispatch interview reminder.', [
                    'interviewId' => (string) $row->getId(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($stats['processed'] > 0) {
            $this->doctrine->getManager()->flush();
        }

        return $stats;
    }

    private function buildReminderContext(Interview $interview): array
    {
        try {
            $application = $interview->getApplication_id();
            $offer = $application->getOffer_id();
            $candidate = $application->getCandidate_id();
            $recruiter = $interview->getRecruiter_id();

            $candidateUser = $this->resolveUserEntity($candidate);
            $recruiterUser = $this->resolveUserEntity($recruiter);

            $candidateRecipient = $this->buildRecipient(
                'candidate',
                $candidateUser,
                (string) $application->getPhone()
            );
            $recruiterRecipient = $this->buildRecipient('recruiter', $recruiterUser, '');

            $mode = $this->normalizeMode((string) $interview->getMode());
            $meetingLink = trim((string) $interview->getMeeting_link());
            $location = trim((string) $interview->getLocation());

            $placeLabel = $mode === 'onsite'
                ? ($location !== '' ? $location : 'Not specified')
                : ($meetingLink !== '' ? $meetingLink : 'Meeting link not provided');

            return [
                'ok' => true,
                'offerTitle' => trim((string) $offer->getTitle()) !== '' ? (string) $offer->getTitle() : 'Interview',
                'scheduledAt' => $interview->getScheduled_at()->format('d M Y H:i'),
                'durationMinutes' => (int) $interview->getDuration_minutes(),
                'modeLabel' => strtoupper($mode),
                'placeLabel' => $placeLabel,
                'notes' => trim((string) $interview->getNotes()),
                'recipients' => [$candidateRecipient, $recruiterRecipient],
            ];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    private function resolveUserEntity(object $actor): ?Users
    {
        if (method_exists($actor, 'getId')) {
            $idValue = $actor->getId();
            if ($idValue instanceof Users) {
                return $idValue;
            }
        }

        if (method_exists($actor, 'getUser_id')) {
            $userId = trim((string) $actor->getUser_id());
            if ($userId !== '') {
                $user = $this->doctrine->getRepository(Users::class)->find($userId);
                if ($user instanceof Users) {
                    return $user;
                }
            }
        }

        return null;
    }

    private function buildRecipient(string $role, ?Users $user, string $fallbackPhone): array
    {
        $firstName = $user instanceof Users ? trim((string) $user->getFirst_name()) : '';
        $lastName = $user instanceof Users ? trim((string) $user->getLast_name()) : '';
        $fullName = trim($firstName . ' ' . $lastName);

        $email = $user instanceof Users ? trim((string) $user->getEmail()) : '';
        $phone = $user instanceof Users ? trim((string) $user->getPhone()) : '';
        if ($phone === '') {
            $phone = trim($fallbackPhone);
        }

        return [
            'role' => $role,
            'name' => $fullName !== '' ? $fullName : ucfirst($role),
            'email' => $email,
            'phone' => $this->normalizePhone($phone),
        ];
    }

    private function normalizeMode(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === 'onsite' || $normalized === 'on_site' || $normalized === 'on-site') {
            return 'onsite';
        }

        return 'online';
    }

    private function normalizePhone(string $value): string
    {
        $trimmed = preg_replace('/\s+/', '', trim($value)) ?? '';
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '+')) {
            $digits = preg_replace('/\D+/', '', substr($trimmed, 1)) ?? '';
            return $digits !== '' ? '+' . $digits : '';
        }

        return preg_replace('/\D+/', '', $trimmed) ?? '';
    }

    private function buildEmailBody(
        string $recipientName,
        string $roleLabel,
        string $offerTitle,
        string $scheduledAt,
        int $durationMinutes,
        string $modeLabel,
        string $placeLabel,
        string $notes
    ): string {
        $lines = [
            'Hello ' . $recipientName . ',',
            '',
            'This is a 24-hour reminder for an upcoming interview.',
            'Role: ' . $roleLabel,
            'Job offer: ' . $offerTitle,
            'Date and time: ' . $scheduledAt,
            'Duration: ' . $durationMinutes . ' minutes',
            'Mode: ' . $modeLabel,
        ];

        if ($modeLabel === 'ONLINE') {
            $lines[] = 'Meeting link: ' . $placeLabel;
        } else {
            $lines[] = 'Location: ' . $placeLabel;
        }

        if ($notes !== '') {
            $lines[] = 'Notes: ' . $notes;
        }

        $lines[] = '';
        $lines[] = 'Talent Bridge';

        return implode("\n", $lines);
    }

    private function buildSmsText(string $offerTitle, string $scheduledAt, string $modeLabel, string $placeLabel): string
    {
        $base = sprintf('Reminder: Interview for %s in 24h. %s, %s.', $offerTitle, $scheduledAt, $modeLabel);

        if ($modeLabel === 'ONLINE') {
            return $base . ' Link: ' . $placeLabel;
        }

        return $base . ' Location: ' . $placeLabel;
    }
}
