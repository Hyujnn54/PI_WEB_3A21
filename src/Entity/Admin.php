<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Users;
use Doctrine\Common\Collections\Collection;
use App\Entity\Job_offer_warning;

#[ORM\Entity]
class Admin
{

    #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Users::class, inversedBy: "admins")]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Users $id;

    #[ORM\Column(type: "string", length: 100)]
    private string $assigned_area;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getAssigned_area()
    {
        return $this->assigned_area;
    }

    public function setAssigned_area($value)
    {
        $this->assigned_area = $value;
    }

    #[ORM\OneToMany(mappedBy: "admin_id", targetEntity: Job_offer_warning::class)]
    private Collection $job_offer_warnings;

        public function getJob_offer_warnings(): Collection
        {
            return $this->job_offer_warnings;
        }
    
        public function addJob_offer_warning(Job_offer_warning $job_offer_warning): self
        {
            if (!$this->job_offer_warnings->contains($job_offer_warning)) {
                $this->job_offer_warnings[] = $job_offer_warning;
                $job_offer_warning->setAdmin_id($this);
            }
    
            return $this;
        }
    
        public function removeJob_offer_warning(Job_offer_warning $job_offer_warning): self
        {
            if ($this->job_offer_warnings->removeElement($job_offer_warning)) {
                // set the owning side to null (unless already changed)
                if ($job_offer_warning->getAdmin_id() === $this) {
                    $job_offer_warning->setAdmin_id(null);
                }
            }
    
            return $this;
        }
}
