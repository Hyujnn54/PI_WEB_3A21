<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Candidate;

#[ORM\Entity]
class Event_registration
{

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

    #[ORM\Column(type: "string", length: 255)]
    private string $attendance_status;

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
        $this->registered_at = $value;
    }

    public function getAttendance_status(): string
    {
        return $this->attendance_status;
    }

    public function setAttendance_status(string $value): void
    {
        $this->attendance_status = $value;
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
