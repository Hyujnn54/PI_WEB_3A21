<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Users;

#[ORM\Entity]
class Application_status_history
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_application::class, inversedBy: "application_status_historys")]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Job_application $application_id;

    #[ORM\Column(type: "string")]
    private string $status;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $changed_at;

        #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'changed_by', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Users $changed_by;

    #[ORM\Column(type: "string", length: 255)]
    private string $note;

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

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($value)
    {
        $this->status = $value;
    }

    public function getChanged_at()
    {
        return $this->changed_at;
    }

    public function setChanged_at($value)
    {
        $this->changed_at = $value;
    }

    public function getChanged_by()
    {
        return $this->changed_by;
    }

    public function setChanged_by($value)
    {
        $this->changed_by = $value;
    }

    public function getNote()
    {
        return $this->note;
    }

    public function setNote($value)
    {
        $this->note = $value;
    }
}
