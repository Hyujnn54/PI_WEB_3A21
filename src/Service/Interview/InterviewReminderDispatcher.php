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
        private readonly ReminderMessageBuilder $messageBuilder,
        private readonly InterviewLocationQrCodeService $locationQrCodeService,
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

        $transportEnabled = $this->emailSender->isEnabled() && $this->smsSender->isEnabled();
        if (!$transportEnabled) {
            $this->logger->warning('Interview reminders are disabled because both Brevo and SMS Mobile API must be configured.');
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

                $subject = $this->messageBuilder->buildSubject($context['offerTitle']);
                $allDelivered = true;
                $recipientCount = 0;

                foreach ($context['recipients'] as $recipient) {
                    ++$recipientCount;
                    $recipientName = $recipient['name'];
                    $email = $recipient['email'];
                    $phone = $recipient['phone'];
                    $roleLabel = ucfirst($recipient['role']);

                    if ($email === '' || $phone === '') {
                        $allDelivered = false;
                        $this->logger->warning('Interview reminder recipient is missing required contact details.', [
                            'interviewId' => (string) $row->getId(),
                            'role' => $recipient['role'],
                            'hasEmail' => $email !== '',
                            'hasPhone' => $phone !== '',
                        ]);
                        continue;
                    }

                    $emailTextBody = $this->messageBuilder->buildEmailText(
                        $recipientName,
                        $roleLabel,
                        $context['offerTitle'],
                        $context['scheduledAt'],
                        $context['durationMinutes'],
                        $context['modeLabel'],
                        $context['placeLabel'],
                        $context['notes'],
                        $context['mapsUrl']
                    );

                    $emailHtmlBody = $this->messageBuilder->buildEmailHtml(
                        $recipientName,
                        $roleLabel,
                        $context['offerTitle'],
                        $context['scheduledAt'],
                        $context['durationMinutes'],
                        $context['modeLabel'],
                        $context['placeLabel'],
                        $context['notes'],
                        $context['mapsUrl'],
                        $context['locationQrCodeDataUri']
                    );

                    $emailSent = $this->emailSender->send($email, $recipientName, $subject, $emailTextBody, $emailHtmlBody);
                    if ($emailSent) {
                        ++$stats['emails_sent'];
                    }

                    $smsText = $this->messageBuilder->buildSmsText(
                        $context['offerTitle'],
                        $context['scheduledAt'],
                        $context['modeLabel'],
                        $context['placeLabel'],
                        $context['mapsUrl']
                    );

                    $smsSent = $this->smsSender->send($phone, $smsText);
                    if ($smsSent) {
                        ++$stats['sms_sent'];
                    }

                    if (!$emailSent || !$smsSent) {
                        $allDelivered = false;
                        $this->logger->warning('Interview reminder delivery failed for recipient.', [
                            'interviewId' => (string) $row->getId(),
                            'role' => $recipient['role'],
                            'emailSent' => $emailSent,
                            'smsSent' => $smsSent,
                        ]);
                    }
                }

                if ($recipientCount === 0 || !$allDelivered) {
                    ++$stats['skipped'];
                    continue;
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
            $mapsUrl = '';
            $locationQrCodeDataUri = '';

            if ($mode === 'onsite') {
                $locationPayload = $this->locationQrCodeService->buildOnsiteLocationPayload($location);
                $mapsUrl = (string) ($locationPayload['mapsUrl'] ?? '');
                $locationQrCodeDataUri = (string) ($locationPayload['qrCodeDataUri'] ?? '');
            }

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
                'mapsUrl' => $mapsUrl,
                'locationQrCodeDataUri' => $locationQrCodeDataUri,
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

}
