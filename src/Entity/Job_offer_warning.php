<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Admin;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Warning_correction;

#[ORM\Entity]
class Job_offer_warning
{
    private const ALLOWED_WARNING_TYPES = [
        'Policy violation',
        'Incorrect information',
        'Missing required details',
        'Deadline issue',
        'Description trompeuse',
        'Other',
    ];

    private const WARNING_TEXT_REGEX = '/^[\p{L}\p{N}\s,\.\/#()\-!?;:\'"\n\r]{10,500}$/u';

    public function __construct()
    {
        $this->warning_corrections = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_offer::class, inversedBy: "job_offer_warnings")]
    #[ORM\JoinColumn(name: 'job_offer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Job_offer $job_offer_id;

        #[ORM\ManyToOne(targetEntity: Recruiter::class, inversedBy: "job_offer_warnings")]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Recruiter $recruiter_id;

        #[ORM\ManyToOne(targetEntity: Admin::class, inversedBy: "job_offer_warnings")]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'id')]
    private Admin $admin_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $reason;

    #[ORM\Column(type: "text")]
    private string $message;

    #[ORM\Column(type: "string")]
    private string $status;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $seen_at;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $resolved_at;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getJob_offer_id(): Job_offer
    {
        return $this->job_offer_id;
    }

    public function setJob_offer_id(Job_offer $value): void
    {
        $this->job_offer_id = $value;
    }

    public function getRecruiter_id(): Recruiter
    {
        return $this->recruiter_id;
    }

    public function setRecruiter_id(Recruiter $value): void
    {
        $this->recruiter_id = $value;
    }

    public function getAdmin_id(): Admin
    {
        return $this->admin_id;
    }

    public function setAdmin_id(Admin $value): void
    {
        $this->admin_id = $value;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $value): void
    {
        $this->reason = $value;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $value): void
    {
        $this->message = $value;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $value): void
    {
        $this->status = $value;
    }

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $value): void
    {
        $this->created_at = $value;
    }

    public function getSeen_at(): \DateTimeInterface
    {
        return $this->seen_at;
    }

    public function setSeen_at(\DateTimeInterface $value): void
    {
        $this->seen_at = $value;
    }

    public function getResolved_at(): \DateTimeInterface
    {
        return $this->resolved_at;
    }

    public function setResolved_at(\DateTimeInterface $value): void
    {
        $this->resolved_at = $value;
    }

    /** @var Collection<int, Warning_correction> */
    #[ORM\OneToMany(mappedBy: "warning_id", targetEntity: Warning_correction::class)]
    private Collection $warning_corrections;

    /**
     * @return Collection<int, Warning_correction>
     */
    public function getWarning_corrections(): Collection
    {
        return $this->warning_corrections;
    }

    /**
     * @return array{ok: bool, error?: string, warningType?: string, warningText?: string}
     */
    /**
     * @return array{ok: false, error: string}|array{ok: true, warningType: string, warningText: string}
     */
    public static function validateWarningInput(string $warningType, string $warningText): array
    {
        $type = trim($warningType);
        $text = trim($warningText);

        if ($type === '' || $text === '') {
            return ['ok' => false, 'error' => 'Warning type and message are required.'];
        }

        if (!in_array($type, self::ALLOWED_WARNING_TYPES, true)) {
            return ['ok' => false, 'error' => 'Please select a valid warning type.'];
        }

        if (!preg_match(self::WARNING_TEXT_REGEX, $text)) {
            return ['ok' => false, 'error' => 'Warning message must be 10-500 chars and contain valid text.'];
        }

        return [
            'ok' => true,
            'warningType' => $type,
            'warningText' => $text,
        ];
    }
}
