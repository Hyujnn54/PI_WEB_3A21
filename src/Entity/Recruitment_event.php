<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Recruiter;
use Doctrine\Common\Collections\Collection;
use App\Entity\Event_review;

#[ORM\Entity]
class Recruitment_event
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

        #[ORM\ManyToOne(targetEntity: Recruiter::class, inversedBy: "recruitment_events")]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruiter $recruiter_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $title;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "string", length: 255)]
    private string $event_type;

    #[ORM\Column(type: "string", length: 255)]
    private string $location;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $event_date;

    #[ORM\Column(type: "integer")]
    private int $capacity;

    #[ORM\Column(type: "string", length: 255)]
    private string $meet_link;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    public function getId(): ?int
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

    public function getEvent_type()
    {
        return $this->event_type;
    }

    public function setEvent_type($value)
    {
        $this->event_type = $value;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setLocation($value)
    {
        $this->location = $value;
    }

    public function getEvent_date()
    {
        return $this->event_date;
    }

    public function setEvent_date($value)
    {
        $this->event_date = $value;
    }

    public function getCapacity()
    {
        return $this->capacity;
    }

    public function setCapacity($value)
    {
        $this->capacity = $value;
    }

    public function getMeet_link()
    {
        return $this->meet_link;
    }

    public function setMeet_link($value)
    {
        $this->meet_link = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }

    #[ORM\OneToMany(mappedBy: "event_id", targetEntity: Event_registration::class)]
    private Collection $event_registrations;

        public function getEvent_registrations(): Collection
        {
            return $this->event_registrations;
        }
    
        public function addEvent_registration(Event_registration $event_registration): self
        {
            if (!$this->event_registrations->contains($event_registration)) {
                $this->event_registrations[] = $event_registration;
                $event_registration->setEvent_id($this);
            }
    
            return $this;
        }
    
        public function removeEvent_registration(Event_registration $event_registration): self
        {
            if ($this->event_registrations->removeElement($event_registration)) {
                // set the owning side to null (unless already changed)
                if ($event_registration->getEvent_id() === $this) {
                    $event_registration->setEvent_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "event_id", targetEntity: Event_review::class)]
    private Collection $event_reviews;
}
