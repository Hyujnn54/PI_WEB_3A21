<?php

namespace App\Entity;
use App\Entity\Users;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'recruiter')]
class Recruiter extends Users
{
    /** @var Collection<int, Interview> */
    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Interview::class)]
    private Collection $interviews;

    /** @var Collection<int, Interview_feedback> */
    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Interview_feedback::class)]
    private Collection $interview_feedbacks;

    /** @var Collection<int, Job_offer> */
    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Job_offer::class)]
    private Collection $job_offers;

    /** @var Collection<int, Job_offer_warning> */
    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Job_offer_warning::class)]
    private Collection $job_offer_warnings;

    /** @var Collection<int, Recruitment_event> */
    #[ORM\OneToMany(mappedBy: "recruiter_id", targetEntity: Recruitment_event::class)]
    private Collection $recruitment_events;

    #[ORM\Column(name: "company_name", length: 255)]
    #[Assert\NotBlank(message: 'Company name is required.')]
    #[Assert\Length(max: 255)]
    private ?string $companyName = null;

    #[ORM\Column(name: "company_location", length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $companyLocation = null;

    public function __construct()
    {
        parent::__construct();
        $this->interviews = new ArrayCollection();
        $this->interview_feedbacks = new ArrayCollection();
        $this->job_offers = new ArrayCollection();
        $this->job_offer_warnings = new ArrayCollection();
        $this->recruitment_events = new ArrayCollection();
    }

    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(string $companyName): self { $this->companyName = $companyName; return $this; }

    public function getCompanyLocation(): ?string { return $this->companyLocation; }
    public function setCompanyLocation(?string $companyLocation): self { $this->companyLocation = $companyLocation; return $this; }

    /**
     * @return Collection<int, Interview>
     */
    public function getInterviews(): Collection { return $this->interviews; }

    public function addInterview(Interview $interview): self
    {
        if (!$this->interviews->contains($interview)) {
            $this->interviews[] = $interview;
            $interview->setRecruiter_id($this);
        }

        return $this;
    }

    public function removeInterview(Interview $interview): self
    {
        $this->interviews->removeElement($interview);

        return $this;
    }

    /**
     * @return Collection<int, Interview_feedback>
     */
    public function getInterview_feedbacks(): Collection { return $this->interview_feedbacks; }

    public function addInterview_feedback(Interview_feedback $interviewFeedback): self
    {
        if (!$this->interview_feedbacks->contains($interviewFeedback)) {
            $this->interview_feedbacks[] = $interviewFeedback;
            $interviewFeedback->setRecruiter_id($this);
        }

        return $this;
    }

    public function removeInterview_feedback(Interview_feedback $interviewFeedback): self
    {
        $this->interview_feedbacks->removeElement($interviewFeedback);

        return $this;
    }

    /**
     * @return Collection<int, Job_offer>
     */
    public function getJob_offers(): Collection { return $this->job_offers; }

    public function addJob_offer(Job_offer $jobOffer): self
    {
        if (!$this->job_offers->contains($jobOffer)) {
            $this->job_offers[] = $jobOffer;
            $jobOffer->setRecruiter_id($this);
        }

        return $this;
    }

    public function removeJob_offer(Job_offer $jobOffer): self
    {
        $this->job_offers->removeElement($jobOffer);

        return $this;
    }

    /**
     * @return Collection<int, Job_offer_warning>
     */
    public function getJob_offer_warnings(): Collection { return $this->job_offer_warnings; }

    public function addJob_offer_warning(Job_offer_warning $jobOfferWarning): self
    {
        if (!$this->job_offer_warnings->contains($jobOfferWarning)) {
            $this->job_offer_warnings[] = $jobOfferWarning;
            $jobOfferWarning->setRecruiter_id($this);
        }

        return $this;
    }

    public function removeJob_offer_warning(Job_offer_warning $jobOfferWarning): self
    {
        $this->job_offer_warnings->removeElement($jobOfferWarning);

        return $this;
    }

    /**
     * @return Collection<int, Recruitment_event>
     */
    public function getRecruitment_events(): Collection { return $this->recruitment_events; }

    public function addRecruitment_event(Recruitment_event $recruitmentEvent): self
    {
        if (!$this->recruitment_events->contains($recruitmentEvent)) {
            $this->recruitment_events[] = $recruitmentEvent;
            $recruitmentEvent->setRecruiter_id($this);
        }

        return $this;
    }

    public function removeRecruitment_event(Recruitment_event $recruitmentEvent): self
    {
        $this->recruitment_events->removeElement($recruitmentEvent);

        return $this;
    }

    public function getRoles(): array { return ['ROLE_RECRUITER']; }
}
