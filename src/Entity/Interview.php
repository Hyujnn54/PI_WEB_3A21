<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Recruiter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Interview_feedback;

#[ORM\Entity]
class Interview
{
    private const LOCATION_REGEX = '/^(?=.{3,120}$)[^\p{Cc}\p{Cf}<>]+$/u';
    private const NOTES_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{0,1000}$/u';


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
    private string $mode = '';

    #[ORM\Column(type: "string", length: 255)]
    private string $meeting_link = '';

    #[ORM\Column(type: "string", length: 255)]
    private string $location = '';

    #[ORM\Column(type: "string")]
    private string $status = 'SCHEDULED';

    #[ORM\Column(type: "text")]
    private string $notes = '';

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: "boolean")]
    private ?bool $reminder_sent = false;

    public function __construct()
    {
        $this->interview_feedbacks = new ArrayCollection();
        $this->created_at = new \DateTime();
    }

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
        $raw = strtolower(trim($this->mode));

        if (in_array($raw, ['onsite', 'on_site', 'on-site', 'on site', 'in_person', 'in-person', 'in person'], true)) {
            return 'onsite';
        }

        if (in_array($raw, ['online', 'on_line', 'on-line', 'on line'], true)) {
            return 'online';
        }

        // Fallback for legacy rows where mode is empty/invalid but location/link imply the mode.
        $location = trim($this->location);
        $meetingLink = trim($this->meeting_link);
        if ($location !== '' && $meetingLink === '') {
            return 'onsite';
        }

        return 'online';
    }

    public function setMode($value)
    {
        $raw = strtolower(trim((string) $value));
        $isOnsite = in_array($raw, ['onsite', 'on_site', 'on-site', 'on site', 'in_person', 'in-person', 'in person'], true);
        $this->mode = $isOnsite ? 'ON_SITE' : 'ONLINE';
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
        return isset($this->reminder_sent) ? (bool) $this->reminder_sent : false;
    }

    public function setReminder_sent($value)
    {
        $this->reminder_sent = $value;
    }

    public static function validateInput(array $data, int $maxFutureDays = 90): array
    {
        try {
            $scheduledAtImmutable = new \DateTimeImmutable((string) ($data['scheduled_at'] ?? ''));
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Invalid interview date/time.'];
        }

        $now = new \DateTimeImmutable();
        if ($scheduledAtImmutable <= $now) {
            return ['ok' => false, 'error' => 'Interview date/time must be in the future.'];
        }

        if ($scheduledAtImmutable > $now->modify('+' . $maxFutureDays . ' days')) {
            return ['ok' => false, 'error' => 'Interview cannot be scheduled more than ' . $maxFutureDays . ' days ahead.'];
        }

        // Entity fields are mapped as mutable datetime, so convert before persistence.
        $scheduledAt = \DateTime::createFromImmutable($scheduledAtImmutable);

        $duration = (int) ($data['duration_minutes'] ?? 0);
        if ($duration < 15 || $duration > 240) {
            return ['ok' => false, 'error' => 'Duration must be between 15 and 240 minutes.'];
        }

        $mode = strtolower(trim((string) ($data['mode'] ?? 'online')));
        if (!in_array($mode, ['online', 'onsite'], true)) {
            return ['ok' => false, 'error' => 'Interview mode must be online or onsite.'];
        }

        $meetingLink = trim((string) ($data['meeting_link'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($mode === 'online' && $meetingLink === '') {
            return ['ok' => false, 'error' => 'Meeting link is required for online interviews.'];
        }

        if ($mode === 'online' && !self::isValidMeetingLink($meetingLink)) {
            return ['ok' => false, 'error' => 'Meeting link must be a valid http(s) URL.'];
        }

        if ($mode === 'onsite' && $location === '') {
            return ['ok' => false, 'error' => 'Location is required for onsite interviews.'];
        }

        if ($mode === 'onsite' && !self::isValidLocation($location)) {
            return ['ok' => false, 'error' => 'Location must be 3-120 characters and cannot contain angle brackets or hidden control characters.'];
        }

        if (!self::isValidNotes($notes)) {
            return ['ok' => false, 'error' => 'Notes contain unsupported characters or exceed 1000 characters.'];
        }

        return [
            'ok' => true,
            'scheduledAt' => $scheduledAt,
            'duration' => $duration,
            'mode' => $mode,
            'meetingLink' => $meetingLink,
            'location' => $location,
            'notes' => $notes,
        ];
    }

    private static function isValidMeetingLink(string $meetingLink): bool
    {
        if (!filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            return false;
        }

        return (bool) preg_match('/^https?:\/\/[\S]+$/i', $meetingLink);
    }

    private static function isValidLocation(string $location): bool
    {
        return (bool) preg_match(self::LOCATION_REGEX, $location);
    }

    private static function isValidNotes(string $value): bool
    {
        if (mb_strlen($value) > 1000) {
            return false;
        }

        return (bool) preg_match(self::NOTES_REGEX, $value);
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
