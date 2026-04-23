<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Users;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'candidate')]
class Candidate extends Users
{
    #[ORM\Column(name: 'location', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'latitude', type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(name: "education_level", length: 100, nullable: true)]
    private ?string $educationLevel = null;

    #[ORM\Column(name: "experience_years", nullable: true)]
    private ?int $experienceYears = null;

    #[ORM\Column(name: "cv_path", length: 255, nullable: true)]
    private ?string $cvPath = null;

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }

    public function getCity(): ?string { return $this->location; }
    public function setCity(?string $city): self { $this->location = $city; return $this; }

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): self { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): self { $this->longitude = $longitude; return $this; }

    public function getEducationLevel(): ?string { return $this->educationLevel; }
    public function setEducationLevel(?string $educationLevel): self { $this->educationLevel = $educationLevel; return $this; }

    public function getExperienceYears(): ?int { return $this->experienceYears; }
    public function setExperienceYears(?int $experienceYears): self { $this->experienceYears = $experienceYears; return $this; }

    public function getCvPath(): ?string { return $this->cvPath; }
    public function setCvPath(?string $cvPath): self { $this->cvPath = $cvPath; return $this; }

    public function getRoles(): array { return ['ROLE_CANDIDATE']; }
}