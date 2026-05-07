<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Job_offer_comment
{
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_FLAGGED = 'FLAGGED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_WARNED = 'WARNED';

    public const VISIBILITY_VISIBLE = 'VISIBLE';
    public const VISIBILITY_HIDDEN = 'HIDDEN';

    private const COMMENT_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{5,1000}$/u';

    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Job_offer::class)]
    #[ORM\JoinColumn(name: 'job_offer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Job_offer $job_offer_id;

    #[ORM\ManyToOne(targetEntity: Candidate::class)]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Candidate $candidate_id;

    #[ORM\Column(type: 'text')]
    private string $comment_text;

    #[ORM\Column(type: 'float')]
    private float $toxicity_score;

    #[ORM\Column(type: 'float')]
    private float $spam_score;

    #[ORM\Column(type: 'string', length: 32)]
    private string $sentiment;

    #[ORM\Column(type: 'text')]
    private string $labels;

    #[ORM\Column(type: 'string', length: 32)]
    private string $moderation_status;

    #[ORM\Column(type: 'string', length: 16)]
    private string $visibility_status;

    #[ORM\Column(type: 'boolean')]
    private bool $is_auto_flagged;

    #[ORM\Column(type: 'string', length: 32)]
    private string $analyzer_source;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $analyzed_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $moderated_at = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(name: 'moderator_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Admin $moderator_id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderator_action_note = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getJob_offer_id(): Job_offer
    {
        return $this->job_offer_id;
    }

    public function setJob_offer_id(Job_offer $job_offer_id): self
    {
        $this->job_offer_id = $job_offer_id;

        return $this;
    }

    public function getCandidate_id(): Candidate
    {
        return $this->candidate_id;
    }

    public function setCandidate_id(Candidate $candidate_id): self
    {
        $this->candidate_id = $candidate_id;

        return $this;
    }

    public function getComment_text(): string
    {
        return $this->comment_text;
    }

    public function setComment_text(string $comment_text): self
    {
        $this->comment_text = $comment_text;

        return $this;
    }

    public function getToxicity_score(): float
    {
        return $this->toxicity_score;
    }

    public function setToxicity_score(float $toxicity_score): self
    {
        $this->toxicity_score = $toxicity_score;

        return $this;
    }

    public function getSpam_score(): float
    {
        return $this->spam_score;
    }

    public function setSpam_score(float $spam_score): self
    {
        $this->spam_score = $spam_score;

        return $this;
    }

    public function getSentiment(): string
    {
        return $this->sentiment;
    }

    public function setSentiment(string $sentiment): self
    {
        $this->sentiment = $sentiment;

        return $this;
    }

    public function getLabels(): string
    {
        return $this->labels;
    }

    public function setLabels(string $labels): self
    {
        $this->labels = $labels;

        return $this;
    }

    public function getModeration_status(): string
    {
        return $this->moderation_status;
    }

    public function setModeration_status(string $moderation_status): self
    {
        $this->moderation_status = $moderation_status;

        return $this;
    }

    public function getVisibility_status(): string
    {
        return $this->visibility_status;
    }

    public function setVisibility_status(string $visibility_status): self
    {
        $this->visibility_status = $visibility_status;

        return $this;
    }

    public function isAuto_flagged(): bool
    {
        return $this->is_auto_flagged;
    }

    public function setIs_auto_flagged(bool $is_auto_flagged): self
    {
        $this->is_auto_flagged = $is_auto_flagged;

        return $this;
    }

    public function getAnalyzer_source(): string
    {
        return $this->analyzer_source;
    }

    public function setAnalyzer_source(string $analyzer_source): self
    {
        $this->analyzer_source = $analyzer_source;

        return $this;
    }

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getAnalyzed_at(): ?\DateTimeInterface
    {
        return $this->analyzed_at;
    }

    public function setAnalyzed_at(?\DateTimeInterface $analyzed_at): self
    {
        $this->analyzed_at = $analyzed_at;

        return $this;
    }

    public function getModerated_at(): ?\DateTimeInterface
    {
        return $this->moderated_at;
    }

    public function setModerated_at(?\DateTimeInterface $moderated_at): self
    {
        $this->moderated_at = $moderated_at;

        return $this;
    }

    public function getModerator_id(): ?Admin
    {
        return $this->moderator_id;
    }

    public function setModerator_id(?Admin $moderator_id): self
    {
        $this->moderator_id = $moderator_id;

        return $this;
    }

    public function getModerator_action_note(): ?string
    {
        return $this->moderator_action_note;
    }

    public function setModerator_action_note(?string $moderator_action_note): self
    {
        $this->moderator_action_note = $moderator_action_note;

        return $this;
    }

    public static function validateCommentText(string $comment): array
    {
        $value = trim($comment);

        if ($value === '') {
            return ['ok' => false, 'error' => 'Comment is required.'];
        }

        if (!preg_match(self::COMMENT_REGEX, $value)) {
            return ['ok' => false, 'error' => 'Comment must be 5-1000 chars and use standard punctuation.'];
        }

        return ['ok' => true, 'value' => $value];
    }
}
