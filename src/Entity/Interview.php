<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Recruiter;
use Doctrine\Common\Collections\Collection;
use App\Entity\Interview_feedback;

#[ORM\Entity]
class Interview
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_application::class, inversedBy: "interviews")]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Job_application $application_id;

        #[ORM\ManyToOne(targetEntity: Recruiter::class, inversedBy: "interviews")]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruiter $recruiter_id;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $scheduled_at;

    #[ORM\Column(type: "integer")]
    private int $duration_minutes;

    #[ORM\Column(type: "string")]
    private string $mode;

    #[ORM\Column(type: "string", length: 255)]
    private string $meeting_link;

    #[ORM\Column(type: "string", length: 255)]
    private string $location;

    #[ORM\Column(type: "string")]
    private string $status;

    #[ORM\Column(type: "text")]
    private string $notes;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "boolean")]
    private bool $reminder_sent;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getApplication_id()
    {
        return $this->application_id;
    }

    public function setApplication_id($value)
    {
        $this->application_id = $value;
    }

    public function getRecruiter_id()
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id($value)
    {
        $this->recruiter_id = $value;
    }

    public function getScheduled_at()
    {
        return $this->scheduled_at;
    }

    public function setScheduled_at($value)
    {
        $this->scheduled_at = $value;
    }

    public function getDuration_minutes()
    {
        return $this->duration_minutes;
    }

    public function setDuration_minutes($value)
    {
        $this->duration_minutes = $value;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($value)
    {
        $this->mode = $value;
    }

    public function getMeeting_link()
    {
        return $this->meeting_link;
    }

    public function setMeeting_link($value)
    {
        $this->meeting_link = $value;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setLocation($value)
    {
        $this->location = $value;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($value)
    {
        $this->status = $value;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function setNotes($value)
    {
        $this->notes = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }

    public function getReminder_sent()
    {
        return $this->reminder_sent;
    }

    public function setReminder_sent($value)
    {
        $this->reminder_sent = $value;
    }

    #[ORM\OneToMany(mappedBy: "interview_id", targetEntity: Interview_feedback::class)]
    private Collection $interview_feedbacks;

        public function getInterview_feedbacks(): Collection
        {
            return $this->interview_feedbacks;
        }
    
        public function addInterview_feedback(Interview_feedback $interview_feedback): self
        {
            if (!$this->interview_feedbacks->contains($interview_feedback)) {
                $this->interview_feedbacks[] = $interview_feedback;
                $interview_feedback->setInterview_id($this);
            }
    
            return $this;
        }
    
        public function removeInterview_feedback(Interview_feedback $interview_feedback): self
        {
            if ($this->interview_feedbacks->removeElement($interview_feedback)) {
                // set the owning side to null (unless already changed)
                if ($interview_feedback->getInterview_id() === $this) {
                    $interview_feedback->setInterview_id(null);
                }
            }
    
            return $this;
        }
}
