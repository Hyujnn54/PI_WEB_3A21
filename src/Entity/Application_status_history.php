<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

use App\Entity\Users;

#[ORM\Entity]
class Application_status_history
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_application::class, inversedBy: "application_status_historys")]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id')]
        #[Assert\NotNull(message: 'Application is required for status history.')]
    private Job_application $application_id;

    #[ORM\Column(type: "string")]
        #[Assert\NotBlank(message: 'Status cannot be empty.')]
        #[Assert\Length(
            min: 2,
            max: 50,
            minMessage: 'Status must be at least {{ limit }} characters.',
            maxMessage: 'Status must not exceed {{ limit }} characters.'
        )]
    private string $status;

    #[ORM\Column(type: "datetime")]
        #[Gedmo\Timestampable(on: "create")]
        #[Assert\NotNull(message: 'Change date is required.')]
        #[Assert\Type(type: \DateTimeInterface::class, message: 'Invalid change date.')]
    private \DateTimeInterface $changed_at;

        #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'changed_by_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        #[Assert\NotNull(message: 'Author is required for status history.')]
    private Users $changed_by;

    #[ORM\Column(type: "string", length: 255)]
        #[Assert\NotBlank(message: 'Note cannot be empty.')]
        #[Assert\Length(
            min: 2,
            max: 255,
            minMessage: 'Note must be at least {{ limit }} characters.',
            maxMessage: 'Note must not exceed {{ limit }} characters.'
        )]
    private string $note;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getApplication_id(): Job_application
    {
        return $this->application_id;
    }

    public function setApplication_id(Job_application $value): void
    {
        $this->application_id = $value;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $value): void
    {
        $this->status = strtoupper(trim($value));
    }

    public function getChanged_at(): \DateTimeInterface
    {
        return $this->changed_at;
    }

    public function setChanged_at(\DateTimeInterface $value): void
    {
        $this->changed_at = $value;
    }

    public function getChanged_by(): Users
    {
        return $this->changed_by;
    }

    public function setChanged_by(Users $value): void
    {
        $this->changed_by = $value;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $value): void
    {
        $this->note = trim($value);
    }
}
