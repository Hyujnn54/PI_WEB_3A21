<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\Job_applicationRepository;

use App\Entity\Candidate;
use Doctrine\Common\Collections\Collection;
use App\Entity\Interview;

#[ORM\Entity(repositoryClass: Job_applicationRepository::class)]
class Job_application
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_offer::class, inversedBy: "job_applications")]
    #[ORM\JoinColumn(name: 'offer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        #[Assert\NotNull(message: 'A job offer is required.')]
    private Job_offer $offer_id;

        #[ORM\ManyToOne(targetEntity: Candidate::class, inversedBy: "job_applications")]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        #[Assert\NotNull(message: 'A candidate is required.')]
    private Candidate $candidate_id;

    #[ORM\Column(type: "string", length: 30)]
        #[Assert\NotBlank(message: 'Phone number cannot be empty.')]
        #[Assert\Regex(
            pattern: '/^(?:\+216|216|0)?[259][0-9]{7}$/',
            message: 'Please enter a valid Tunisian phone number (+216XXXXXXXX, 216XXXXXXXX, 0XXXXXXXX or XXXXXXXX).'
        )]
    private string $phone;

    #[ORM\Column(type: "text")]
        #[Assert\NotBlank(message: 'Cover letter cannot be empty.')]
        #[Assert\Length(
            min: 50,
            max: 2000,
            minMessage: 'Cover letter must be at least {{ limit }} characters.',
            maxMessage: 'Cover letter must not exceed {{ limit }} characters.'
        )]
    private string $cover_letter;

    #[ORM\Column(type: "string", length: 255)]
    private string $cv_path = '';

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $applied_at;

    #[ORM\Column(type: "string")]
    private string $current_status;

    #[ORM\Column(type: "boolean")]
    private bool $is_archived;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getOffer_id()
    {
        return $this->offer_id;
    }

    public function setOffer_id($value)
    {
        $this->offer_id = $value;
    }

    public function getCandidate_id()
    {
        return $this->candidate_id;
    }

    public function setCandidate_id($value)
    {
        $this->candidate_id = $value;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function setPhone($value)
    {
        $this->phone = trim((string) ($value ?? ''));
    }

    public function getCover_letter()
    {
        return $this->cover_letter;
    }

    public function getCoverLetter()
    {
        return $this->cover_letter;
    }

    public function setCover_letter($value)
    {
        $this->cover_letter = trim((string) ($value ?? ''));
    }

    public function setCoverLetter($value)
    {
        $this->cover_letter = $value;
    }

    public function getCv_path()
    {
        return isset($this->cv_path) ? $this->cv_path : '';
    }

    public function setCv_path($value)
    {
        $this->cv_path = (string) ($value ?? '');
    }

    public function getCvPath()
    {
        return $this->getCv_path();
    }

    public function setCvPath($value)
    {
        $this->setCv_path($value);
    }

    public function getApplied_at()
    {
        return $this->applied_at;
    }

    public function setApplied_at($value)
    {
        $this->applied_at = $value;
    }

    public function getCurrent_status()
    {
        return $this->current_status;
    }

    public function setCurrent_status($value)
    {
        $this->current_status = $value;
    }

    public function getIs_archived()
    {
        return $this->is_archived;
    }

    public function setIs_archived($value)
    {
        $this->is_archived = $value;
    }

    #[ORM\OneToMany(mappedBy: "application_id", targetEntity: Application_status_history::class)]
    private Collection $application_status_historys;

    #[ORM\OneToMany(mappedBy: "application_id", targetEntity: Interview::class)]
    private Collection $interviews;
}
