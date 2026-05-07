<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Recruiter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Job_offer_warning;

#[ORM\Entity]
class Job_offer
{
    private const TITLE_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,150}$/u';
    private const LOCATION_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,120}$/u';
    private const TEXTAREA_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,1000}$/u';

    public function __construct()
    {
        $this->offer_skills = new ArrayCollection();
        $this->job_applications = new ArrayCollection();
        $this->warning_corrections = new ArrayCollection();
        $this->job_offer_warnings = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Recruiter::class, inversedBy: "job_offers")]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruiter $recruiter_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $title;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: "string")]
    private string $contract_type;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $deadline;

    #[ORM\Column(type: "string")]
    private string $status;

    #[ORM\Column(type: "integer")]
    private int $quality_score;

    #[ORM\Column(type: "text")]
    private string $ai_suggestions;

    #[ORM\Column(type: "boolean")]
    private bool $is_flagged;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $flagged_at;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getRecruiter_id(): Recruiter
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id(Recruiter $value): void
    {
        $this->recruiter_id = $value;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $value): void
    {
        $this->title = $value;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $value): void
    {
        $this->description = $value;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $value): self
    {
        $this->location = $value;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $value): self
    {
        $this->latitude = $value;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $value): self
    {
        $this->longitude = $value;

        return $this;
    }

    public function getContract_type(): string
    {
        return $this->contract_type;
    }

    public function setContract_type(string $value): void
    {
        $this->contract_type = $value;
    }

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $value): void
    {
        $this->created_at = $value;
    }

    public function getDeadline(): \DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(\DateTimeInterface $value): void
    {
        $this->deadline = $value;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $value): void
    {
        $this->status = $value;
    }

    public function getQuality_score(): int
    {
        return $this->quality_score;
    }

    public function setQuality_score(int $value): void
    {
        $this->quality_score = $value;
    }

    public function getAi_suggestions(): string
    {
        return $this->ai_suggestions;
    }

    public function setAi_suggestions(string $value): void
    {
        $this->ai_suggestions = $value;
    }

    public function getIs_flagged(): bool
    {
        return $this->is_flagged;
    }

    public function setIs_flagged(bool $value): void
    {
        $this->is_flagged = $value;
    }

    public function getFlagged_at(): \DateTimeInterface
    {
        return $this->flagged_at;
    }

    public function setFlagged_at(\DateTimeInterface $value): void
    {
        $this->flagged_at = $value;
    }

    /** @var Collection<int, Offer_skill> */
    #[ORM\OneToMany(mappedBy: "offer_id", targetEntity: Offer_skill::class)]
    private Collection $offer_skills;

    /**
     * @return Collection<int, Offer_skill>
     */
    public function getOffer_skills(): Collection
        {
            return $this->offer_skills;
        }
    
        public function addOffer_skill(Offer_skill $offer_skill): self
        {
            if (!$this->offer_skills->contains($offer_skill)) {
                $this->offer_skills[] = $offer_skill;
                $offer_skill->setOffer_id($this);
            }
    
            return $this;
        }
    
        public function removeOffer_skill(Offer_skill $offer_skill): self
        {
            $this->offer_skills->removeElement($offer_skill);
    
            return $this;
        }

    /** @var Collection<int, Job_application> */
    #[ORM\OneToMany(mappedBy: "offer_id", targetEntity: Job_application::class)]
    private Collection $job_applications;

    /**
     * @return Collection<int, Job_application>
     */
    public function getJob_applications(): Collection
    {
        return $this->job_applications;
    }

    /** @var Collection<int, Warning_correction> */
    #[ORM\OneToMany(mappedBy: "job_offer_id", targetEntity: Warning_correction::class)]
    private Collection $warning_corrections;

    /**
     * @return Collection<int, Warning_correction>
     */
    public function getWarning_corrections(): Collection
        {
            return $this->warning_corrections;
        }
    
        public function addWarning_correction(Warning_correction $warning_correction): self
        {
            if (!$this->warning_corrections->contains($warning_correction)) {
                $this->warning_corrections[] = $warning_correction;
                $warning_correction->setJob_offer_id($this);
            }
    
            return $this;
        }
    
        public function removeWarning_correction(Warning_correction $warning_correction): self
        {
            $this->warning_corrections->removeElement($warning_correction);
    
            return $this;
        }

    /** @var Collection<int, Job_offer_warning> */
    #[ORM\OneToMany(mappedBy: "job_offer_id", targetEntity: Job_offer_warning::class)]
    private Collection $job_offer_warnings;

    /**
     * @return Collection<int, Job_offer_warning>
     */
    public function getJob_offer_warnings(): Collection
    {
        return $this->job_offer_warnings;
    }

    /**
     * Keep create-form validation rules in the entity so controller stays focused on flow.
     *
     * @param array<string, mixed> $formData
     * @param array<int, string> $allowedContractTypes
     * @param array<int, string> $allowedStatuses
     * @param array<int, string> $allowedSkillLevels
     *
     * @return array<string, mixed>
     */
    public static function validateCreateFormData(
        array $formData,
        array $allowedContractTypes,
        array $allowedStatuses,
        array $allowedSkillLevels
    ): array {
        $errors = [];

        $title = trim((string) ($formData['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (!preg_match(self::TITLE_REGEX, $title)) {
            $errors['title'] = 'Title must be 3-150 chars and contain valid text.';
        }

        $contractType = trim((string) ($formData['contract_type'] ?? ''));
        if ($contractType === '') {
            $errors['contract_type'] = 'Contract type is required.';
        } elseif (!in_array($contractType, $allowedContractTypes, true)) {
            $errors['contract_type'] = 'Please select a valid contract type.';
        }

        $status = trim((string) ($formData['status'] ?? ''));
        if ($status === '') {
            $errors['status'] = 'Status is required.';
        } elseif (!in_array($status, $allowedStatuses, true)) {
            $errors['status'] = 'Please select a valid status.';
        }

        $description = trim((string) ($formData['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = 'Description is required.';
        } elseif (!preg_match(self::TEXTAREA_REGEX, $description)) {
            $errors['description'] = 'Description must be 10-1000 chars with valid text.';
        }

        $location = trim((string) ($formData['location'] ?? ''));
        if ($location === '') {
            $errors['location'] = 'Location is required.';
        } elseif (!preg_match(self::LOCATION_REGEX, $location)) {
            $errors['location'] = 'Location must be 3-120 chars with valid text.';
        }

        $deadlineRaw = trim((string) ($formData['deadline'] ?? ''));
        if ($deadlineRaw === '') {
            $errors['deadline'] = 'Deadline is required.';
        } else {
            $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $deadlineRaw);
            if (!$deadline) {
                $errors['deadline'] = 'Invalid deadline format.';
            } elseif ($deadline <= new \DateTimeImmutable()) {
                $errors['deadline'] = 'Deadline must be greater than today.';
            }
        }

        $skills = (array) ($formData['skills'] ?? []);
        if (count($skills) === 0) {
            $errors['skills'][0]['name'] = 'Skill name is required.';
            $errors['skills'][0]['level'] = 'Skill level is required.';
        }

        foreach ($skills as $index => $skill) {
            $name = trim((string) ($skill['name'] ?? ''));
            $level = trim((string) ($skill['level'] ?? ''));

            if ($name === '') {
                $errors['skills'][$index]['name'] = 'Skill name is required.';
            }

            if ($level === '') {
                $errors['skills'][$index]['level'] = 'Skill level is required.';
            } elseif (!in_array($level, $allowedSkillLevels, true)) {
                $errors['skills'][$index]['level'] = 'Invalid skill level.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<int, string> $allowedContractTypes
     * @param array<int, string> $allowedSkillLevels
     *
     * @return array<string, mixed>
     */
    public static function validateEditFormData(
        array $formData,
        array $allowedContractTypes,
        array $allowedSkillLevels
    ): array {
        $errors = [];

        $title = trim((string) ($formData['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (!preg_match(self::TITLE_REGEX, $title)) {
            $errors['title'] = 'Title must be 3-150 chars and contain valid text.';
        }

        $contractType = trim((string) ($formData['contract_type'] ?? ''));
        if ($contractType === '') {
            $errors['contract_type'] = 'Contract type is required.';
        } elseif (!in_array($contractType, $allowedContractTypes, true)) {
            $errors['contract_type'] = 'Please select a valid contract type.';
        }

        $description = trim((string) ($formData['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = 'Description is required.';
        } elseif (!preg_match(self::TEXTAREA_REGEX, $description)) {
            $errors['description'] = 'Description must be 10-1000 chars with valid text.';
        }

        $location = trim((string) ($formData['location'] ?? ''));
        if ($location === '') {
            $errors['location'] = 'Location is required.';
        } elseif (!preg_match(self::LOCATION_REGEX, $location)) {
            $errors['location'] = 'Location must be 3-120 chars with valid text.';
        }

        $deadlineRaw = trim((string) ($formData['deadline'] ?? ''));
        if ($deadlineRaw === '') {
            $errors['deadline'] = 'Deadline is required.';
        } else {
            $deadline = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $deadlineRaw);
            if (!$deadline) {
                $errors['deadline'] = 'Invalid deadline format.';
            } elseif ($deadline <= new \DateTimeImmutable()) {
                $errors['deadline'] = 'Deadline must be greater than today.';
            }
        }

        $skills = (array) ($formData['skills'] ?? []);
        if (count($skills) === 0) {
            $errors['skills'][0]['name'] = 'Skill name is required.';
            $errors['skills'][0]['level'] = 'Skill level is required.';
        }

        foreach ($skills as $index => $skill) {
            $name = trim((string) ($skill['name'] ?? ''));
            $level = trim((string) ($skill['level'] ?? ''));

            if ($name === '') {
                $errors['skills'][$index]['name'] = 'Skill name is required.';
            }

            if ($level === '') {
                $errors['skills'][$index]['level'] = 'Skill level is required.';
            } elseif (!in_array($level, $allowedSkillLevels, true)) {
                $errors['skills'][$index]['level'] = 'Invalid skill level.';
            }
        }

        return $errors;
    }
}
