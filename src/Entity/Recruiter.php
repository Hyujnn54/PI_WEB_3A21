<?php

namespace App\Entity;
use App\Entity\Users;


use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'recruiter')]
class Recruiter extends Users
{
    #[ORM\Column(name: "company_name", length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(name: "company_location", length: 255, nullable: true)]
    private ?string $companyLocation = null;

    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(string $companyName): self { $this->companyName = $companyName; return $this; }

    public function getCompanyLocation(): ?string { return $this->companyLocation; }
    public function setCompanyLocation(?string $companyLocation): self { $this->companyLocation = $companyLocation; return $this; }

    public function getRoles(): array { return ['ROLE_RECRUITER']; }
}