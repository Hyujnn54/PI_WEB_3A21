<?php

namespace App\Service;

use App\Entity\Recruiter;
use App\Entity\Recruitment_event;

class EventManager
{
    private const EVENT_TYPES = ['Workshop', 'Hiring Day', 'Webinar'];

    public function validate(Recruitment_event $event): bool
    {
        if (!$this->readObject($event, 'getRecruiter_id', Recruiter::class)) {
            throw new \InvalidArgumentException('A recruiter is required.');
        }

        $title = trim($this->readString($event, 'getTitle'));
        if ($title === '') {
            throw new \InvalidArgumentException('Event title is required.');
        }

        if (strlen($title) < 3) {
            throw new \InvalidArgumentException('Event title must be at least 3 characters.');
        }

        if (strlen($title) > 255) {
            throw new \InvalidArgumentException('Event title cannot exceed 255 characters.');
        }

        $description = trim($this->readString($event, 'getDescription'));
        if ($description === '') {
            throw new \InvalidArgumentException('Description is required.');
        }

        if (strlen($description) < 10) {
            throw new \InvalidArgumentException('Description must be at least 10 characters.');
        }

        $eventType = trim($this->readString($event, 'getEvent_type'));
        if ($eventType === '') {
            throw new \InvalidArgumentException('Event type is required.');
        }

        if (!in_array($eventType, self::EVENT_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid event type selected.');
        }

        $location = trim($this->readString($event, 'getLocation'));
        if ($location === '') {
            throw new \InvalidArgumentException('Location is required.');
        }

        if (strlen($location) < 2) {
            throw new \InvalidArgumentException('Location must be at least 2 characters.');
        }

        $eventDate = $this->readDateTime($event, 'getEvent_date');
        if (!$eventDate instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('Event date is required.');
        }

        if ($eventDate <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Event date must be in the future.');
        }

        $capacity = $this->readInt($event, 'getCapacity');
        if ($capacity < 1) {
            throw new \InvalidArgumentException('Capacity must be at least 1.');
        }

        if ($capacity > 1000) {
            throw new \InvalidArgumentException('Capacity cannot exceed 1000.');
        }

        $meetLink = trim($this->readString($event, 'getMeet_link'));
        if ($meetLink !== '' && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Please enter a valid URL.');
        }

        return true;
    }

    private function readObject(Recruitment_event $event, string $getter, string $expectedClass): bool
    {
        try {
            $value = $event->{$getter}();
        } catch (\Error) {
            return false;
        }

        return $value instanceof $expectedClass;
    }

    private function readDateTime(Recruitment_event $event, string $getter): ?\DateTimeInterface
    {
        try {
            $value = $event->{$getter}();
        } catch (\Error) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value : null;
    }

    private function readInt(Recruitment_event $event, string $getter): int
    {
        try {
            return (int) $event->{$getter}();
        } catch (\Error) {
            return 0;
        }
    }

    private function readString(Recruitment_event $event, string $getter): string
    {
        try {
            return (string) $event->{$getter}();
        } catch (\Error) {
            return '';
        }
    }
}
