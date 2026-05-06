<?php

namespace App\Service;

use App\Entity\Candidate;
use App\Entity\Job_application;
use App\Entity\Job_offer;

class JobApplicationManager
{
    private const APPLICATION_STATUSES = [
        'SUBMITTED',
        'IN_REVIEW',
        'SHORTLISTED',
        'REJECTED',
        'INTERVIEW',
        'HIRED',
    ];

    public function validate(Job_application $application): bool
    {
        if (!$this->readObject($application, 'getOffer_id', Job_offer::class)) {
            throw new \InvalidArgumentException('A job offer is required.');
        }

        if (!$this->readObject($application, 'getCandidate_id', Candidate::class)) {
            throw new \InvalidArgumentException('A candidate is required.');
        }

        $phone = trim($this->readString($application, 'getPhone'));
        if ($phone === '') {
            throw new \InvalidArgumentException('Phone number is required.');
        }

        if (!preg_match('/^(?:\+216|216|0)?[259][0-9]{7}$/', $phone)) {
            throw new \InvalidArgumentException('Phone number is invalid.');
        }

        $coverLetter = trim($this->readString($application, 'getCover_letter'));
        if ($coverLetter === '') {
            throw new \InvalidArgumentException('Cover letter is required.');
        }

        $coverLetterLength = mb_strlen($coverLetter);
        if ($coverLetterLength < 50 || $coverLetterLength > 2000) {
            throw new \InvalidArgumentException('Cover letter must be between 50 and 2000 characters.');
        }

        $cvPath = trim($this->readString($application, 'getCv_path'));
        if ($cvPath === '') {
            throw new \InvalidArgumentException('CV is required.');
        }

        $status = strtoupper(trim($this->readString($application, 'getCurrent_status')));
        if ($status === '') {
            throw new \InvalidArgumentException('Application status is required.');
        }

        if (!in_array($status, self::APPLICATION_STATUSES, true)) {
            throw new \InvalidArgumentException('Application status is invalid.');
        }

        $appliedAt = $this->readDateTime($application, 'getApplied_at');
        if (!$appliedAt instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('Application date is required.');
        }

        if ($appliedAt > new \DateTimeImmutable('+1 minute')) {
            throw new \InvalidArgumentException('Application date cannot be in the future.');
        }

        return true;
    }

    private function readObject(Job_application $application, string $getter, string $expectedClass): bool
    {
        try {
            $value = $application->{$getter}();
        } catch (\Error) {
            return false;
        }

        return $value instanceof $expectedClass;
    }

    private function readString(Job_application $application, string $getter): string
    {
        try {
            return (string) $application->{$getter}();
        } catch (\Error) {
            return '';
        }
    }

    private function readDateTime(Job_application $application, string $getter): ?\DateTimeInterface
    {
        try {
            $value = $application->{$getter}();
        } catch (\Error) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value : null;
    }
}
