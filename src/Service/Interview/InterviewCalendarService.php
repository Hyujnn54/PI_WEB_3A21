<?php

namespace App\Service\Interview;

class InterviewCalendarService
{
    public function buildUpcomingFromCards(array $cards): array
    {
        $upcomingInterviews = [];

        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $timestamp = (int) ($card['scheduled_ts'] ?? 0);
            if ($timestamp <= 0) {
                continue;
            }

            $scheduledAt = (new \DateTimeImmutable())->setTimestamp($timestamp);
            $mode = $this->normalizeMode((string) ($card['form_mode'] ?? 'online'));
            $status = trim((string) ($card['status_label'] ?? 'Scheduled'));
            $title = $this->normalizeTitle((string) ($card['title'] ?? 'Interview'));

            $upcomingInterviews[] = [
                'interview_id' => (string) ($card['id'] ?? ''),
                'timestamp' => $timestamp,
                'date' => $scheduledAt->format('d M Y H:i'),
                'ymd' => $scheduledAt->format('Y-m-d'),
                'title' => $title,
                'mode' => strtoupper($mode),
                'status' => $status !== '' ? $status : 'Scheduled',
                'location' => $mode === 'onsite' ? $this->normalizePlaceLabel((string) ($card['form_location'] ?? '')) : '',
                'meeting_link' => $mode === 'online' ? $this->normalizePlaceLabel((string) ($card['form_meeting_link'] ?? '')) : '',
            ];
        }

        usort($upcomingInterviews, static fn (array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

        return $upcomingInterviews;
    }

    private function normalizeMode(string $mode): string
    {
        $value = strtolower(trim($mode));
        if (in_array($value, ['onsite', 'on_site', 'on-site', 'on site', 'in_person', 'in-person', 'in person'], true)) {
            return 'onsite';
        }

        return 'online';
    }

    private function normalizePlaceLabel(string $value): string
    {
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : 'N/A';
    }

    private function normalizeTitle(string $title): string
    {
        $trimmed = trim($title);
        $prefix = 'Interview:';

        if (str_starts_with($trimmed, $prefix)) {
            $trimmed = trim(substr($trimmed, strlen($prefix)));
        }

        return $trimmed !== '' ? $trimmed : 'Interview';
    }
}
