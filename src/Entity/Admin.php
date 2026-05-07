<?php

namespace App\Entity;
use App\Entity\Users;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin')]
class Admin extends Users
{
    #[ORM\OneToMany(mappedBy: "admin_id", targetEntity: Job_offer_warning::class)]
    private Collection $job_offer_warnings;

    #[ORM\Column(name: "assigned_area", length: 100, nullable: true)]
    private ?string $assignedArea = null;

    public function __construct()
    {
        parent::__construct();
        $this->job_offer_warnings = new ArrayCollection();
    }

    public function getAssignedArea(): ?string { return $this->assignedArea; }
    public function setAssignedArea(?string $assignedArea): self { $this->assignedArea = $assignedArea; return $this; }

    public function getJob_offer_warnings(): Collection { return $this->job_offer_warnings; }

    public function addJob_offer_warning(Job_offer_warning $jobOfferWarning): self
    {
        if (!$this->job_offer_warnings->contains($jobOfferWarning)) {
            $this->job_offer_warnings[] = $jobOfferWarning;
            $jobOfferWarning->setAdmin_id($this);
        }

        return $this;
    }

    public function removeJob_offer_warning(Job_offer_warning $jobOfferWarning): self
    {
        $this->job_offer_warnings->removeElement($jobOfferWarning);

        return $this;
    }

    public function getRoles(): array { return ['ROLE_ADMIN']; }
}
