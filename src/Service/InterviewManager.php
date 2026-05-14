<?php

namespace App\Service;

use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Recruiter;

class InterviewManager
{
    private const MAX_FUTURE_DAYS = 90;
    private const INTERVIEW_STATUSES = ['SCHEDULED', 'DONE', 'CANCELLED'];
    private const LOCATION_REGEX = '/^(?=.{3,120}$)[^\p{Cc}\p{Cf}<>]+$/u';
    private const NOTES_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{0,1000}$/u';

    public function validate(Interview $interview): bool
    {
        if (!$this->readObject($interview, 'getApplication_id', Job_application::class)) {
            throw new \InvalidArgumentException('An application is required.');
        }

        if (!$this->readObject($interview, 'getRecruiter_id', Recruiter::class)) {
            throw new \InvalidArgumentException('A recruiter is required.');
        }

        $scheduledAt = $this->readDateTime($interview, 'getScheduled_at');
        if (!$scheduledAt instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('Interview date/time is required.');
        }

        $now = new \DateTimeImmutable();
        if ($scheduledAt <= $now) {
            throw new \InvalidArgumentException('Interview date/time must be in the future.');
        }

        if ($scheduledAt > $now->modify('+' . self::MAX_FUTURE_DAYS . ' days')) {
            throw new \InvalidArgumentException('Interview cannot be scheduled more than 90 days ahead.');
        }

        $duration = $this->readInt($interview, 'getDuration_minutes');
        if ($duration < 15 || $duration > 240) {
            throw new \InvalidArgumentException('Duration must be between 15 and 240 minutes.');
        }

        $mode = strtolower(trim($this->readString($interview, 'getMode')));
        if (!in_array($mode, ['online', 'onsite'], true)) {
            throw new \InvalidArgumentException('Interview mode must be online or onsite.');
        }

        $meetingLink = trim($this->readString($interview, 'getMeeting_link'));
        $location = trim($this->readString($interview, 'getLocation'));

        if ($mode === 'online' && $meetingLink === '') {
            throw new \InvalidArgumentException('Meeting link is required for online interviews.');
        }

        if ($mode === 'online' && !$this->isValidMeetingLink($meetingLink)) {
            throw new \InvalidArgumentException('Meeting link must be a valid http(s) URL.');
        }

        if ($mode === 'onsite' && $location === '') {
            throw new \InvalidArgumentException('Location is required for onsite interviews.');
        }

        if ($mode === 'onsite' && !preg_match(self::LOCATION_REGEX, $location)) {
            throw new \InvalidArgumentException('Location must be 3-120 characters and cannot contain angle brackets or hidden control characters.');
        }

        $notes = trim($this->readString($interview, 'getNotes'));
        if (mb_strlen($notes) > 1000 || !preg_match(self::NOTES_REGEX, $notes)) {
            throw new \InvalidArgumentException('Notes contain unsupported characters or exceed 1000 characters.');
        }

        $status = strtoupper(trim($this->readString($interview, 'getStatus')));
        if ($status === '') {
            throw new \InvalidArgumentException('Interview status is required.');
        }

        if (!in_array($status, self::INTERVIEW_STATUSES, true)) {
            throw new \InvalidArgumentException('Interview status is invalid.');
        }

        return true;
    }

    private function readObject(Interview $interview, string $getter, string $expectedClass): bool
    {
        try {
            $value = $interview->{$getter}();
        } catch (\Error) {
            return false;
        }

        return $value instanceof $expectedClass;
    }

    private function readDateTime(Interview $interview, string $getter): ?\DateTimeInterface
    {
        try {
            $value = $interview->{$getter}();
        } catch (\Error) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value : null;
    }

    private function readInt(Interview $interview, string $getter): int
    {
        try {
            return (int) $interview->{$getter}();
        } catch (\Error) {
            return 0;
        }
    }

    private function readString(Interview $interview, string $getter): string
    {
        try {
            return (string) $interview->{$getter}();
        } catch (\Error) {
            return '';
        }
    }

    private function isValidMeetingLink(string $meetingLink): bool
    {
        if (!filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            return false;
        }

        return (bool) preg_match('/^https?:\/\/[\S]+$/i', $meetingLink);
    }
}
