<?php

namespace App\Service;

use App\Entity\Job_offer;

class JobOfferManager
{
    private const CONTRACT_TYPES = ['CDI', 'CDD', 'Internship', 'Freelance', 'Full-time', 'Part-time', 'Remote Contract'];
    private const JOB_STATUSES = ['open', 'paused', 'closed'];
    private const TITLE_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,150}$/u';
    private const LOCATION_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,120}$/u';
    private const DESCRIPTION_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,1000}$/u';

    public function validate(Job_offer $offer): bool
    {
        $title = trim((string) $offer->getTitle());
        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }

        if (!preg_match(self::TITLE_REGEX, $title)) {
            throw new \InvalidArgumentException('Title must be 3-150 characters and contain valid text.');
        }

        $contractType = trim((string) $offer->getContract_type());
        if ($contractType === '') {
            throw new \InvalidArgumentException('Contract type is required.');
        }

        if (!in_array($contractType, self::CONTRACT_TYPES, true)) {
            throw new \InvalidArgumentException('Contract type is invalid.');
        }

        $status = trim((string) $offer->getStatus());
        if ($status === '') {
            throw new \InvalidArgumentException('Status is required.');
        }

        if (!in_array($status, self::JOB_STATUSES, true)) {
            throw new \InvalidArgumentException('Status is invalid.');
        }

        $description = trim((string) $offer->getDescription());
        if ($description === '') {
            throw new \InvalidArgumentException('Description is required.');
        }

        if (!preg_match(self::DESCRIPTION_REGEX, $description)) {
            throw new \InvalidArgumentException('Description must be 10-1000 characters and contain valid text.');
        }

        $location = trim((string) $offer->getLocation());
        if ($location === '') {
            throw new \InvalidArgumentException('Location is required.');
        }

        if (!preg_match(self::LOCATION_REGEX, $location)) {
            throw new \InvalidArgumentException('Location must be 3-120 characters and contain valid text.');
        }

        $deadline = $offer->getDeadline();
        if (!$deadline instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('Deadline is required.');
        }

        if ($deadline <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Deadline must be greater than today.');
        }

        return true;
    }
}
