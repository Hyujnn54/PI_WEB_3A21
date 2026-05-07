<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Job_offer;
use App\Entity\Recruiter;

#[ORM\Entity]
class Warning_correction
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_offer_warning::class, inversedBy: "warning_corrections")]
    #[ORM\JoinColumn(name: 'warning_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Job_offer_warning $warning_id;

        #[ORM\ManyToOne(targetEntity: Job_offer::class, inversedBy: "warning_corrections")]
    #[ORM\JoinColumn(name: 'job_offer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Job_offer $job_offer_id;

        #[ORM\ManyToOne(targetEntity: Recruiter::class)]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruiter $recruiter_id;

    #[ORM\Column(type: "text")]
    private string $correction_note;

    #[ORM\Column(type: "string", length: 255)]
    private string $old_title;

    #[ORM\Column(type: "string", length: 255)]
    private string $new_title;

    #[ORM\Column(type: "text")]
    private string $old_description;

    #[ORM\Column(type: "text")]
    private string $new_description;

    #[ORM\Column(type: "string")]
    private string $status;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $submitted_at;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $reviewed_at;

    #[ORM\Column(type: "text")]
    private string $admin_note;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getWarning_id(): Job_offer_warning
    {
        return $this->warning_id;
    }

    public function setWarning_id(Job_offer_warning $value): void
    {
        $this->warning_id = $value;
    }

    public function getJob_offer_id(): Job_offer
    {
        return $this->job_offer_id;
    }

    public function setJob_offer_id(Job_offer $value): void
    {
        $this->job_offer_id = $value;
    }

    public function getRecruiter_id(): Recruiter
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id(Recruiter $value): void
    {
        $this->recruiter_id = $value;
    }

    public function getCorrection_note(): string
    {
        return $this->correction_note;
    }

    public function setCorrection_note(string $value): void
    {
        $this->correction_note = $value;
    }

    public function getOld_title(): string
    {
        return $this->old_title;
    }

    public function setOld_title(string $value): void
    {
        $this->old_title = $value;
    }

    public function getNew_title(): string
    {
        return $this->new_title;
    }

    public function setNew_title(string $value): void
    {
        $this->new_title = $value;
    }

    public function getOld_description(): string
    {
        return $this->old_description;
    }

    public function setOld_description(string $value): void
    {
        $this->old_description = $value;
    }

    public function getNew_description(): string
    {
        return $this->new_description;
    }

    public function setNew_description(string $value): void
    {
        $this->new_description = $value;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $value): void
    {
        $this->status = $value;
    }

    public function getSubmitted_at(): \DateTimeInterface
    {
        return $this->submitted_at;
    }

    public function setSubmitted_at(\DateTimeInterface $value): void
    {
        $this->submitted_at = $value;
    }

    public function getReviewed_at(): \DateTimeInterface
    {
        return $this->reviewed_at;
    }

    public function setReviewed_at(\DateTimeInterface $value): void
    {
        $this->reviewed_at = $value;
    }

    public function getAdmin_note(): string
    {
        return $this->admin_note;
    }

    public function setAdmin_note(string $value): void
    {
        $this->admin_note = $value;
    }
}
