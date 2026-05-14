<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Candidate;

#[ORM\Entity]
class Event_registration
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Recruitment_event::class, inversedBy: "event_registrations")]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruitment_event $event_id;

        #[ORM\ManyToOne(targetEntity: Candidate::class, inversedBy: "event_registrations")]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', nullable: true)]
    private ?Candidate $candidate_id = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $registered_at;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $attendance_status = null;

    private ?string $candidate_name = null;

    private ?string $candidate_email = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getEvent_id(): Recruitment_event
    {
        return $this->event_id;
    }

    public function setEvent_id(Recruitment_event $value): void
    {
        $this->event_id = $value;
    }

    public function getCandidate_id(): ?Candidate
    {
        return $this->candidate_id;
    }

    public function setCandidate_id(?Candidate $value): void
    {
        $this->candidate_id = $value;
    }

    public function getRegistered_at(): \DateTimeInterface
    {
        return $this->registered_at;
    }

    public function setRegistered_at(\DateTimeInterface $value): void
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = \DateTime::createFromImmutable($value);
        }

        $this->registered_at = $value;
    }

    public function getAttendance_status(): string
    {
        return self::normalizeAttendanceStatus($this->attendance_status);
    }

    public function setAttendance_status(?string $value): void
    {
        $this->attendance_status = self::normalizeAttendanceStatus($value);
    }

    public static function normalizeAttendanceStatus(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            self::STATUS_CONFIRMED, 'confirm', 'accepted', 'approved', 'validated' => self::STATUS_CONFIRMED,
            self::STATUS_REJECTED, 'reject', 'declined', 'denied', 'refused' => self::STATUS_REJECTED,
            self::STATUS_CANCELLED, 'canceled', 'cancel', 'unregistered' => self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW, 'noshow', 'absent' => self::STATUS_NO_SHOW,
            default => self::STATUS_REGISTERED,
        };
    }

    public function getCandidate_name(): ?string
    {
        return $this->candidate_name;
    }

    public function setCandidate_name(?string $value): void
    {
        $this->candidate_name = $value;
    }

    public function getCandidate_email(): ?string
    {
        return $this->candidate_email;
    }

    public function setCandidate_email(?string $value): void
    {
        $this->candidate_email = $value;
    }
}
