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
