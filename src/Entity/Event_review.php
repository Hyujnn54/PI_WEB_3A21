<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Candidate;

#[ORM\Entity]
class Event_review
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Recruitment_event::class, inversedBy: "event_reviews")]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruitment_event $event_id;

        #[ORM\ManyToOne(targetEntity: Candidate::class, inversedBy: "event_reviews")]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id')]
    private Candidate $candidate_id;

    #[ORM\Column(type: "integer")]
    private int $rating;

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

    public function getEvent_id()
    {
        return $this->event_id;
    }

    public function setEvent_id($value)
    {
        $this->event_id = $value;
    }

    public function getCandidate_id()
    {
        return $this->candidate_id;
    }

    public function setCandidate_id($value)
    {
        $this->candidate_id = $value;
    }

    public function getRating()
    {
        return $this->rating;
    }

    public function setRating($value)
    {
        $this->rating = $value;
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
