<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Recruiter;

#[ORM\Entity]
class Interview_feedback
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Interview::class, inversedBy: "interview_feedbacks")]
    #[ORM\JoinColumn(name: 'interview_id', referencedColumnName: 'id')]
    private Interview $interview_id;

        #[ORM\ManyToOne(targetEntity: Recruiter::class, inversedBy: "interview_feedbacks")]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruiter $recruiter_id;

    #[ORM\Column(type: "integer")]
    private int $overall_score;

    #[ORM\Column(type: "string")]
    private string $decision;

    #[ORM\Column(type: "text")]
    private string $comment;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getInterview_id()
    {
        return $this->interview_id;
    }

    public function setInterview_id($value)
    {
        $this->interview_id = $value;
    }

    public function getRecruiter_id()
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id($value)
    {
        $this->recruiter_id = $value;
    }

    public function getOverall_score()
    {
        return $this->overall_score;
    }

    public function setOverall_score($value)
    {
        $this->overall_score = $value;
    }

    public function getDecision()
    {
        return $this->decision;
    }

    public function setDecision($value)
    {
        $this->decision = $value;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($value)
    {
        $this->comment = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }
}
