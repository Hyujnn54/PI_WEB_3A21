<?php

namespace App\Service\Interview;

class ReminderMessageBuilder
{
    public function buildSubject(string $offerTitle): string
    {
        return sprintf('Interview reminder: %s in 24 hours', $offerTitle);
    }

    public function buildEmailText(
        string $recipientName,
        string $roleLabel,
        string $offerTitle,
        string $scheduledAt,
        int $durationMinutes,
        string $modeLabel,
        string $placeLabel,
        string $notes,
        string $mapsUrl = ''
    ): string {
        $lines = [
            'Hello ' . $recipientName . ',',
            '',
            'This is your 24-hour interview reminder.',
            'Role: ' . $roleLabel,
            'Job offer: ' . $offerTitle,
            'Date and time: ' . $scheduledAt,
            'Duration: ' . $durationMinutes . ' minutes',
            'Mode: ' . $modeLabel,
        ];

        if ($modeLabel === 'ONLINE') {
            $lines[] = 'Access: Use the "Join Meeting" button in this email.';
        } else {
            $lines[] = 'Location: ' . $placeLabel;
            if (filter_var($mapsUrl, FILTER_VALIDATE_URL)) {
                $lines[] = 'Map link: ' . $mapsUrl;
            }
        }

        if ($notes !== '') {
            $lines[] = 'Notes: ' . $notes;
        }

        $lines[] = '';
        $lines[] = 'Talent Bridge';

        return implode("\n", $lines);
    }

    public function buildEmailHtml(
        string $recipientName,
        string $roleLabel,
        string $offerTitle,
        string $scheduledAt,
        int $durationMinutes,
        string $modeLabel,
        string $placeLabel,
        string $notes,
        string $mapsUrl = '',
        string $locationQrCodeImageUrl = ''
    ): string {
        $isOnline = strtoupper($modeLabel) === 'ONLINE';
        $safeName = $this->escapeHtml($recipientName);
        $safeRole = $this->escapeHtml($roleLabel);
        $safeOfferTitle = $this->escapeHtml($offerTitle);
        $safeScheduledAt = $this->escapeHtml($scheduledAt);
        $safeDuration = $this->escapeHtml((string) $durationMinutes);
        $safeMode = $this->escapeHtml($modeLabel);
        $safePlaceLabel = $this->escapeHtml($placeLabel);
        $hasMapsUrl = (bool) filter_var($mapsUrl, FILTER_VALIDATE_URL);
        $safeMapsUrl = $hasMapsUrl ? $this->escapeHtml($mapsUrl) : '';
        $hasQrCodeImageUrl = (bool) filter_var($locationQrCodeImageUrl, FILTER_VALIDATE_URL);
        $safeQrCodeImageUrl = $hasQrCodeImageUrl ? $this->escapeHtml($locationQrCodeImageUrl) : '';

        $placeRow = '';
        if ($isOnline) {
            $placeRow = '<tr><td style="padding:12px 14px;font-size:14px;color:#44506a;width:40%;">Meeting Access</td><td style="padding:12px 14px;font-size:14px;color:#1d2433;">Use the Join Meeting button below.</td></tr>';
        } else {
            $mapLinkHtml = $hasMapsUrl
                ? '<div style="margin-top:8px;"><a href="' . $safeMapsUrl . '" style="color:#2f6fed;text-decoration:none;font-weight:600;">Open in Google Maps</a></div>'
                : '';
            $placeRow = '<tr><td style="padding:12px 14px;font-size:14px;color:#44506a;width:40%;">Location</td><td style="padding:12px 14px;font-size:14px;color:#1d2433;word-break:break-word;">' . $safePlaceLabel . $mapLinkHtml . '</td></tr>';
        }

        $joinButtonBlock = '';
        if ($isOnline && filter_var($placeLabel, FILTER_VALIDATE_URL)) {
            $safeUrl = $this->escapeHtml($placeLabel);
            $joinButtonBlock = '<tr>'
                . '<td style="padding: 0 24px 20px 24px;">'
                . '<a href="' . $safeUrl . '" style="display:inline-block;padding:12px 18px;background:#2f6fed;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">Join Meeting</a>'
                . '</td>'
                . '</tr>';
        }

        $notesBlock = '';
        if ($notes !== '') {
            $safeNotes = nl2br($this->escapeHtml($notes));
            $notesBlock = '<tr>'
                . '<td style="padding: 0 24px 18px 24px;">'
                . '<div style="font-size:14px;color:#44506a;"><strong>Notes:</strong><br>' . $safeNotes . '</div>'
                . '</td>'
                . '</tr>';
        }

        $qrCodeBlock = '';
        if (!$isOnline && $safeQrCodeImageUrl !== '') {
            $qrCodeBlock = '<tr>'
                . '<td style="padding: 0 24px 18px 24px;">'
                . '<div style="font-size:14px;color:#44506a;margin-bottom:10px;"><strong>Quick check-in map</strong><br>Scan this QR code to open the interview location.</div>'
                . '<img src="' . $safeQrCodeImageUrl . '" alt="Interview location QR code" width="180" height="180" style="display:block;border:1px solid #dbe5f5;border-radius:10px;background:#ffffff;padding:8px;">'
                . '</td>'
                . '</tr>';
        }

        return '<!doctype html>'
            . '<html lang="en">'
            . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Interview Reminder</title></head>'
            . '<body style="margin:0;padding:0;background:#f2f5fb;font-family:Arial,Helvetica,sans-serif;color:#1d2433;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f5fb;padding:24px 12px;">'
            . '<tr>'
            . '<td align="center">'
            . '<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e3e8f2;">'
            . '<tr>'
            . '<td style="padding:24px;background:#1f2f4a;color:#ffffff;">'
            . '<div style="font-size:13px;letter-spacing:0.5px;opacity:0.9;">Talent Bridge</div>'
            . '<div style="font-size:24px;font-weight:700;margin-top:6px;">Interview Reminder</div>'
            . '<div style="font-size:14px;margin-top:8px;opacity:0.92;">Your interview starts in approximately 24 hours.</div>'
            . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="padding:24px 24px 16px 24px;font-size:15px;line-height:1.6;">'
            . 'Hello <strong>' . $safeName . '</strong>,<br>'
            . 'This is a reminder for your upcoming interview as <strong>' . $safeRole . '</strong>.'
            . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="padding:0 24px 18px 24px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f8faff;border:1px solid #dbe5f5;border-radius:10px;overflow:hidden;">'
            . '<tr><td style="padding:12px 14px;font-size:14px;color:#44506a;width:40%;">Job Offer</td><td style="padding:12px 14px;font-size:14px;color:#1d2433;"><strong>' . $safeOfferTitle . '</strong></td></tr>'
            . '<tr><td style="padding:12px 14px;font-size:14px;color:#44506a;width:40%;">Date & Time</td><td style="padding:12px 14px;font-size:14px;color:#1d2433;">' . $safeScheduledAt . '</td></tr>'
            . '<tr><td style="padding:12px 14px;font-size:14px;color:#44506a;width:40%;">Duration</td><td style="padding:12px 14px;font-size:14px;color:#1d2433;">' . $safeDuration . ' minutes</td></tr>'
            . '<tr><td style="padding:12px 14px;font-size:14px;color:#44506a;width:40%;">Mode</td><td style="padding:12px 14px;font-size:14px;color:#1d2433;">' . $safeMode . '</td></tr>'
            . $placeRow
            . '</table>'
            . '</td>'
            . '</tr>'
            . $joinButtonBlock
            . $qrCodeBlock
            . $notesBlock
            . '<tr>'
            . '<td style="padding:0 24px 26px 24px;font-size:13px;color:#6a748b;line-height:1.5;">'
            . 'Please join a few minutes early and ensure your audio/video setup is ready if your interview is online.'
            . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="padding:16px 24px;background:#f5f7fc;border-top:1px solid #e3e8f2;font-size:12px;color:#7b8499;">'
            . 'This is an automated reminder from Talent Bridge.'
            . '</td>'
            . '</tr>'
            . '</table>'
            . '</td>'
            . '</tr>'
            . '</table>'
            . '</body>'
            . '</html>';
    }

    public function buildSmsText(
        string $recipientRole,
        string $recipientName,
        string $offerTitle,
        string $scheduledAt,
        string $modeLabel,
        string $placeLabel
    ): string
    {
        $normalizedRole = strtolower(trim($recipientRole));
        $safeName = trim($recipientName);

        if ($normalizedRole === 'candidate') {
            $message = 'Hello';
            if ($safeName !== '') {
                $message .= ' ' . $safeName;
            }
            $message .= ', don\'t forget your interview for ' . $offerTitle . ' on ' . $scheduledAt . '.';
        } else {
            $message = 'Reminder: upcoming interview for ' . $offerTitle . ' on ' . $scheduledAt . '.';
        }

        if (strtoupper($modeLabel) === 'ONLINE') {
            return $message . ' Mode: online. Meeting link: ' . $placeLabel;
        }

        return $message . ' Mode: onsite. Location: ' . $placeLabel;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
