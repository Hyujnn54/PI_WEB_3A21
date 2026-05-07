<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Users;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Job_application;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'candidate')]
class Candidate extends Users
{
    #[ORM\OneToMany(mappedBy: "candidate_id", targetEntity: Job_application::class)]
    private Collection $job_applications;

    #[ORM\OneToMany(mappedBy: "candidate", targetEntity: Candidate_skill::class)]
    private Collection $candidate_skills;

    #[ORM\OneToMany(mappedBy: "candidate_id", targetEntity: Event_registration::class)]
    private Collection $event_registrations;

    #[ORM\OneToMany(mappedBy: "candidate_id", targetEntity: Event_review::class)]
    private Collection $event_reviews;

    #[ORM\Column(name: 'location', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(name: 'latitude', type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(name: "education_level", length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $educationLevel = null;

    #[ORM\Column(name: "experience_years", nullable: true)]
    #[Assert\Positive(message: 'Experience years must be a positive number.')]
    #[Assert\LessThanOrEqual(60, message: 'Experience years must not exceed 60.')]
    private ?int $experienceYears = null;

    #[ORM\Column(name: "cv_path", length: 255, nullable: true)]
    private ?string $cvPath = null;

    public function __construct()
    {
        parent::__construct();
        $this->job_applications = new ArrayCollection();
        $this->candidate_skills = new ArrayCollection();
        $this->event_registrations = new ArrayCollection();
        $this->event_reviews = new ArrayCollection();
    }

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

    public function getJob_applications(): Collection { return $this->job_applications; }

    public function addJob_application(Job_application $jobApplication): self
    {
        if (!$this->job_applications->contains($jobApplication)) {
            $this->job_applications[] = $jobApplication;
            $jobApplication->setCandidate_id($this);
        }

        return $this;
    }

    public function removeJob_application(Job_application $jobApplication): self
    {
        $this->job_applications->removeElement($jobApplication);

        return $this;
    }

    public function getCandidate_skills(): Collection { return $this->candidate_skills; }

    public function addCandidate_skill(Candidate_skill $candidateSkill): self
    {
        if (!$this->candidate_skills->contains($candidateSkill)) {
            $this->candidate_skills[] = $candidateSkill;
            $candidateSkill->setCandidate($this);
        }

        return $this;
    }

    public function removeCandidate_skill(Candidate_skill $candidateSkill): self
    {
        $this->candidate_skills->removeElement($candidateSkill);

        return $this;
    }

    public function getEvent_registrations(): Collection { return $this->event_registrations; }

    public function addEvent_registration(Event_registration $eventRegistration): self
    {
        if (!$this->event_registrations->contains($eventRegistration)) {
            $this->event_registrations[] = $eventRegistration;
            $eventRegistration->setCandidate_id($this);
        }

        return $this;
    }

    public function removeEvent_registration(Event_registration $eventRegistration): self
    {
        $this->event_registrations->removeElement($eventRegistration);

        return $this;
    }

    public function getEvent_reviews(): Collection { return $this->event_reviews; }

    public function addEvent_review(Event_review $eventReview): self
    {
        if (!$this->event_reviews->contains($eventReview)) {
            $this->event_reviews[] = $eventReview;
            $eventReview->setCandidate_id($this);
        }

        return $this;
    }

    public function removeEvent_review(Event_review $eventReview): self
    {
        $this->event_reviews->removeElement($eventReview);

        return $this;
    }

    public function getRoles(): array { return ['ROLE_CANDIDATE']; }
}
