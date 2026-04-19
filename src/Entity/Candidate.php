<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Users;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'candidate')]
class Candidate extends Users
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(name: "education_level", length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $educationLevel = null;

    #[ORM\Column(name: "experience_years", nullable: true)]
    #[Assert\Positive(message: 'Experience years must be a positive number.')]
    #[Assert\LessThanOrEqual(60, message: 'Experience years must not exceed 60.')]
    private ?int $experienceYears = null;

    #[ORM\Column(name: "cv_path", length: 255, nullable: true)]
    private ?string $cvPath = null;

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }

    public function getEducationLevel(): ?string { return $this->educationLevel; }
    public function setEducationLevel(?string $educationLevel): self { $this->educationLevel = $educationLevel; return $this; }

    public function getExperienceYears(): ?int { return $this->experienceYears; }
    public function setExperienceYears(?int $experienceYears): self { $this->experienceYears = $experienceYears; return $this; }

    public function getCvPath(): ?string { return $this->cvPath; }
    public function setCvPath(?string $cvPath): self { $this->cvPath = $cvPath; return $this; }

    public function getRoles(): array { return ['ROLE_CANDIDATE']; }
}