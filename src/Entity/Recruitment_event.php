<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use App\Entity\Recruiter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Event_review;

#[ORM\Entity]
class Recruitment_event
{
    public function __construct()
    {
        $this->event_registrations = new ArrayCollection();
        $this->event_reviews = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

        #[ORM\ManyToOne(targetEntity: Recruiter::class, inversedBy: "recruitment_events")]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruiter $recruiter_id;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: 'Event title is required.')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Event title must be at least 3 characters.', maxMessage: 'Event title cannot exceed 255 characters.')]
    private string $title;

    #[ORM\Column(type: "text")]
    #[Assert\NotBlank(message: 'Description is required.')]
    #[Assert\Length(min: 10, minMessage: 'Description must be at least 10 characters.')]
    private string $description;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: 'Event type is required.')]
    #[Assert\Choice(choices: ['Workshop', 'Hiring Day', 'Webinar'], message: 'Invalid event type selected.')]
    private string $event_type;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: 'Location is required.')]
    #[Assert\Length(min: 2, minMessage: 'Location must be at least 2 characters.')]
    private string $location;

    #[ORM\Column(type: "datetime")]
    #[Assert\NotNull(message: 'Event date is required.')]
    #[Assert\GreaterThan('now', message: 'Event date must be in the future.')]
    private ?\DateTimeInterface $event_date = null;

    #[ORM\Column(type: "integer")]
    #[Assert\NotNull(message: 'Capacity is required.')]
    #[Assert\Range(min: 1, max: 1000, notInRangeMessage: 'Capacity must be between {{ min }} and {{ max }}.')]
    private ?int $capacity = null;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\Url(message: 'Please enter a valid URL.')]
    private string $meet_link = '';

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $value): void
    {
        $this->id = $value;
    }

    public function getRecruiter_id(): Recruiter
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id(Recruiter $value): void
    {
        $this->recruiter_id = $value;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $value): void
    {
        $this->title = $value;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $value): void
    {
        $this->description = $value;
    }

    public function getEvent_type(): string
    {
        return $this->event_type;
    }

    public function setEvent_type(string $value): void
    {
        $this->event_type = $value;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $value): void
    {
        $this->location = $value;
    }

    public function getEvent_date(): ?\DateTimeInterface
    {
        return $this->event_date;
    }

    public function setEvent_date(?\DateTimeInterface $value): void
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = \DateTime::createFromImmutable($value);
        }

        $this->event_date = $value;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $value): void
    {
        $this->capacity = $value;
    }

    public function getMeet_link(): string
    {
        return $this->meet_link;
    }

    public function setMeet_link(string $value): void
    {
        $this->meet_link = $value;
    }

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $value): void
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = \DateTime::createFromImmutable($value);
        }

        $this->created_at = $value;
    }

    /** @var Collection<int, Event_registration> */
    #[ORM\OneToMany(mappedBy: "event_id", targetEntity: Event_registration::class)]
    private Collection $event_registrations;

    /**
     * @return Collection<int, Event_registration>
     */
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
            $this->event_registrations->removeElement($event_registration);
    
            return $this;
        }

    /** @var Collection<int, Event_review> */
    #[ORM\OneToMany(mappedBy: "event_id", targetEntity: Event_review::class)]
    private Collection $event_reviews;

    /**
     * @return Collection<int, Event_review>
     */
    public function getEvent_reviews(): Collection
    {
        return $this->event_reviews;
    }
}
