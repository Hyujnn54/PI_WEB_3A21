<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Job_offer;

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

    #[ORM\Column(type: "bigint")]
    private string $recruiter_id;

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

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getWarning_id()
    {
        return $this->warning_id;
    }

    public function setWarning_id($value)
    {
        $this->warning_id = $value;
    }

    public function getJob_offer_id()
    {
        return $this->job_offer_id;
    }

    public function setJob_offer_id($value)
    {
        $this->job_offer_id = $value;
    }

    public function getRecruiter_id()
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id($value)
    {
        $this->recruiter_id = $value;
    }

    public function getCorrection_note()
    {
        return $this->correction_note;
    }

    public function setCorrection_note($value)
    {
        $this->correction_note = $value;
    }

    public function getOld_title()
    {
        return $this->old_title;
    }

    public function setOld_title($value)
    {
        $this->old_title = $value;
    }

    public function getNew_title()
    {
        return $this->new_title;
    }

    public function setNew_title($value)
    {
        $this->new_title = $value;
    }

    public function getOld_description()
    {
        return $this->old_description;
    }

    public function setOld_description($value)
    {
        $this->old_description = $value;
    }

    public function getNew_description()
    {
        return $this->new_description;
    }

    public function setNew_description($value)
    {
        $this->new_description = $value;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($value)
    {
        $this->status = $value;
    }

    public function getSubmitted_at()
    {
        return $this->submitted_at;
    }

    public function setSubmitted_at($value)
    {
        $this->submitted_at = $value;
    }

    public function getReviewed_at()
    {
        return $this->reviewed_at;
    }

    public function setReviewed_at($value)
    {
        $this->reviewed_at = $value;
    }

    public function getAdmin_note()
    {
        return $this->admin_note;
    }

    public function setAdmin_note($value)
    {
        $this->admin_note = $value;
    }
}
