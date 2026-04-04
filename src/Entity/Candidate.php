<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Users;
use Doctrine\Common\Collections\Collection;
use App\Entity\Job_application;

#[ORM\Entity]
class Candidate
{

    #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Users::class, inversedBy: "candidates")]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Users $id;

    #[ORM\Column(type: "bigint")]
    private string $user_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $location;

    #[ORM\Column(type: "string", length: 100)]
    private string $education_level;

    #[ORM\Column(type: "integer")]
    private int $experience_years;

    #[ORM\Column(type: "string", length: 255)]
    private string $cv_path;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getUser_id()
    {
        return $this->user_id;
    }

    public function setUser_id($value)
    {
        $this->user_id = $value;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setLocation($value)
    {
        $this->location = $value;
    }

    public function getEducation_level()
    {
        return $this->education_level;
    }

    public function setEducation_level($value)
    {
        $this->education_level = $value;
    }

    public function getExperience_years()
    {
        return $this->experience_years;
    }

    public function setExperience_years($value)
    {
        $this->experience_years = $value;
    }

    public function getCv_path()
    {
        return $this->cv_path;
    }

    public function setCv_path($value)
    {
        $this->cv_path = $value;
    }

    #[ORM\OneToMany(mappedBy: "candidate_id", targetEntity: Candidate_skill::class)]
    private Collection $candidate_skills;

        public function getCandidate_skills(): Collection
        {
            return $this->candidate_skills;
        }
    
        public function addCandidate_skill(Candidate_skill $candidate_skill): self
        {
            if (!$this->candidate_skills->contains($candidate_skill)) {
                $this->candidate_skills[] = $candidate_skill;
                $candidate_skill->setCandidate_id($this);
            }
    
            return $this;
        }
    
        public function removeCandidate_skill(Candidate_skill $candidate_skill): self
        {
            if ($this->candidate_skills->removeElement($candidate_skill)) {
                // set the owning side to null (unless already changed)
                if ($candidate_skill->getCandidate_id() === $this) {
                    $candidate_skill->setCandidate_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "candidate_id", targetEntity: Event_registration::class)]
    private Collection $event_registrations;

    #[ORM\OneToMany(mappedBy: "candidate_id", targetEntity: Event_review::class)]
    private Collection $event_reviews;

    #[ORM\OneToMany(mappedBy: "candidate_id", targetEntity: Job_application::class)]
    private Collection $job_applications;
}
