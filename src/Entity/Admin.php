<?php

namespace App\Entity;
use App\Entity\Users;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin')]
class Admin extends Users
{
    #[ORM\Column(name: "assigned_area", length: 100, nullable: true)]
    private ?string $assignedArea = null;

    public function getAssignedArea(): ?string { return $this->assignedArea; }
    public function setAssignedArea(?string $assignedArea): self { $this->assignedArea = $assignedArea; return $this; }

    public function getRoles(): array { return ['ROLE_ADMIN']; }
}