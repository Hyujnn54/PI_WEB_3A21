<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\Job_applicationRepository;

use App\Entity\Candidate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Interview;

#[ORM\Entity(repositoryClass: Job_applicationRepository::class)]
class Job_application
{
    public function __construct()
    {
        $this->application_status_historys = new ArrayCollection();
        $this->interviews = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_offer::class, inversedBy: "job_applications")]
    #[ORM\JoinColumn(name: 'offer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        #[Assert\NotNull(message: 'A job offer is required.')]
    private Job_offer $offer_id;

        #[ORM\ManyToOne(targetEntity: Candidate::class, inversedBy: "job_applications")]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id')]
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

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getOffer_id(): Job_offer
    {
        return $this->offer_id;
    }

    public function setOffer_id(Job_offer $value): void
    {
        $this->offer_id = $value;
    }

    public function getCandidate_id(): Candidate
    {
        return $this->candidate_id;
    }

    public function setCandidate_id(Candidate $value): void
    {
        $this->candidate_id = $value;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $value): void
    {
        $this->phone = trim($value);
    }

    public function getCover_letter(): string
    {
        return $this->cover_letter;
    }

    public function getCoverLetter(): string
    {
        return $this->cover_letter;
    }

    public function setCover_letter(string $value): void
    {
        $this->cover_letter = trim($value);
    }

    public function setCoverLetter(string $value): void
    {
        $this->cover_letter = $value;
    }

    public function getCv_path(): string
    {
        return $this->cv_path;
    }

    public function setCv_path(string $value): void
    {
        $this->cv_path = $value;
    }

    public function getCvPath(): string
    {
        return $this->getCv_path();
    }

    public function setCvPath(string $value): void
    {
        $this->setCv_path($value);
    }

    public function getApplied_at(): \DateTimeInterface
    {
        return $this->applied_at;
    }

    public function setApplied_at(\DateTimeInterface $value): void
    {
        $this->applied_at = $value;
    }

    public function getCurrent_status(): string
    {
        return $this->current_status;
    }

    public function setCurrent_status(string $value): void
    {
        $this->current_status = $value;
    }

    public function getIs_archived(): bool
    {
        return $this->is_archived;
    }

    public function setIs_archived(bool $value): void
    {
        $this->is_archived = $value;
    }

    /** @var Collection<int, Application_status_history> */
    #[ORM\OneToMany(mappedBy: "application_id", targetEntity: Application_status_history::class)]
    private Collection $application_status_historys;

    /**
     * @return Collection<int, Application_status_history>
     */
    public function getApplication_status_historys(): Collection
    {
        return $this->application_status_historys;
    }

    /** @var Collection<int, Interview> */
    #[ORM\OneToMany(mappedBy: "application_id", targetEntity: Interview::class)]
    private Collection $interviews;

    /**
     * @return Collection<int, Interview>
     */
    public function getInterviews(): Collection
    {
        return $this->interviews;
    }
}
