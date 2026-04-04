<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Users;
use Doctrine\Common\Collections\Collection;
use App\Entity\Job_offer;

#[ORM\Entity]
class Recruiter
{

    #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Users::class, inversedBy: "recruiters")]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Users $id;

    #[ORM\Column(type: "bigint")]
    private string $user_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $company_name;

    #[ORM\Column(type: "string", length: 255)]
    private string $company_location;

    #[ORM\Column(type: "text")]
    private string $company_description;

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

    public function getCompany_name()
    {
        return $this->company_name;
    }

    public function setCompany_name($value)
    {
        $this->company_name = $value;
    }

    public function getCompany_location()
    {
        return $this->company_location;
    }

    public function setCompany_location($value)
    {
        $this->company_location = $value;
    }

    public function getCompany_description()
    {
        return $this->company_description;
    }

    public function setCompany_description($value)
    {
        $this->company_description = $value;
    }

    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Job_offer::class)]
    private Collection $job_offers;

    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Recruitment_event::class)]
    private Collection $recruitment_events;

    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Interview::class)]
    private Collection $interviews;

    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Interview_feedback::class)]
    private Collection $interview_feedbacks;

    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Job_offer_warning::class)]
    private Collection $job_offer_warnings;
}
