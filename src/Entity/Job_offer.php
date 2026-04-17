<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Recruiter;
use Doctrine\Common\Collections\Collection;
use App\Entity\Job_offer_warning;

#[ORM\Entity]
class Job_offer
{
    private const TITLE_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,150}$/u';
    private const LOCATION_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-]{3,120}$/u';
    private const TEXTAREA_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,1000}$/u';


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

    #[ORM\Column(type: "string", length: 255)]
    private string $location;

    #[ORM\Column(type: "float")]
    private float $latitude;

    #[ORM\Column(type: "float")]
    private float $longitude;

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

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getRecruiter_id()
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id($value)
    {
        $this->recruiter_id = $value;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($value)
    {
        $this->title = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setLocation($value)
    {
        $this->location = $value;
    }

    public function getLatitude()
    {
        return $this->latitude;
    }

    public function setLatitude($value)
    {
        $this->latitude = $value;
    }

    public function getLongitude()
    {
        return $this->longitude;
    }

    public function setLongitude($value)
    {
        $this->longitude = $value;
    }

    public function getContract_type()
    {
        return $this->contract_type;
    }

    public function setContract_type($value)
    {
        $this->contract_type = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }

    public function getDeadline()
    {
        return $this->deadline;
    }

    public function setDeadline($value)
    {
        $this->deadline = $value;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($value)
    {
        $this->status = $value;
    }

    public function getQuality_score()
    {
        return $this->quality_score;
    }

    public function setQuality_score($value)
    {
        $this->quality_score = $value;
    }

    public function getAi_suggestions()
    {
        return $this->ai_suggestions;
    }

    public function setAi_suggestions($value)
    {
        $this->ai_suggestions = $value;
    }

    public function getIs_flagged()
    {
        return $this->is_flagged;
    }

    public function setIs_flagged($value)
    {
        $this->is_flagged = $value;
    }

    public function getFlagged_at()
    {
        return $this->flagged_at;
    }

    public function setFlagged_at($value)
    {
        $this->flagged_at = $value;
    }

    #[ORM\OneToMany(mappedBy: "offer_id", targetEntity: Offer_skill::class)]
    private Collection $offer_skills;

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
            if ($this->offer_skills->removeElement($offer_skill)) {
                // set the owning side to null (unless already changed)
                if ($offer_skill->getOffer_id() === $this) {
                    $offer_skill->setOffer_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "offer_id", targetEntity: Job_application::class)]
    private Collection $job_applications;

    #[ORM\OneToMany(mappedBy: "job_offer_id", targetEntity: Warning_correction::class)]
    private Collection $warning_corrections;

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
            if ($this->warning_corrections->removeElement($warning_correction)) {
                // set the owning side to null (unless already changed)
                if ($warning_correction->getJob_offer_id() === $this) {
                    $warning_correction->setJob_offer_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "job_offer_id", targetEntity: Job_offer_warning::class)]
    private Collection $job_offer_warnings;

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
