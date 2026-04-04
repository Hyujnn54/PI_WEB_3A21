<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Application_status_history;

#[ORM\Entity]
class Users
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $email;

    #[ORM\Column(type: "string", length: 255)]
    private string $password;

    #[ORM\Column(type: "string", length: 100)]
    private string $first_name;

    #[ORM\Column(type: "string", length: 100)]
    private string $last_name;

    #[ORM\Column(type: "string", length: 30)]
    private string $phone;

    #[ORM\Column(type: "boolean")]
    private bool $is_active;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "string", length: 10)]
    private string $forget_code;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $forget_code_expires;

    #[ORM\Column(type: "string", length: 128)]
    private string $face_person_id;

    #[ORM\Column(type: "boolean")]
    private bool $face_enabled;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($value)
    {
        $this->email = $value;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($value)
    {
        $this->password = $value;
    }

    public function getFirst_name()
    {
        return $this->first_name;
    }

    public function setFirst_name($value)
    {
        $this->first_name = $value;
    }

    public function getLast_name()
    {
        return $this->last_name;
    }

    public function setLast_name($value)
    {
        $this->last_name = $value;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function setPhone($value)
    {
        $this->phone = $value;
    }

    public function getIs_active()
    {
        return $this->is_active;
    }

    public function setIs_active($value)
    {
        $this->is_active = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }

    public function getForget_code()
    {
        return $this->forget_code;
    }

    public function setForget_code($value)
    {
        $this->forget_code = $value;
    }

    public function getForget_code_expires()
    {
        return $this->forget_code_expires;
    }

    public function setForget_code_expires($value)
    {
        $this->forget_code_expires = $value;
    }

    public function getFace_person_id()
    {
        return $this->face_person_id;
    }

    public function setFace_person_id($value)
    {
        $this->face_person_id = $value;
    }

    public function getFace_enabled()
    {
        return $this->face_enabled;
    }

    public function setFace_enabled($value)
    {
        $this->face_enabled = $value;
    }

    #[ORM\OneToMany(mappedBy: "id", targetEntity: Admin::class)]
    private Collection $admins;

        public function getAdmins(): Collection
        {
            return $this->admins;
        }
    
        public function addAdmin(Admin $admin): self
        {
            if (!$this->admins->contains($admin)) {
                $this->admins[] = $admin;
                $admin->setId($this);
            }
    
            return $this;
        }
    
        public function removeAdmin(Admin $admin): self
        {
            if ($this->admins->removeElement($admin)) {
                // set the owning side to null (unless already changed)
                if ($admin->getId() === $this) {
                    $admin->setId(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "id", targetEntity: Candidate::class)]
    private Collection $candidates;

    #[ORM\OneToMany(mappedBy: "id", targetEntity: Recruiter::class)]
    private Collection $recruiters;

    #[ORM\OneToMany(mappedBy: "changed_by", targetEntity: Application_status_history::class)]
    private Collection $application_status_historys;

        public function getApplication_status_historys(): Collection
        {
            return $this->application_status_historys;
        }
    
        public function addApplication_status_history(Application_status_history $application_status_history): self
        {
            if (!$this->application_status_historys->contains($application_status_history)) {
                $this->application_status_historys[] = $application_status_history;
                $application_status_history->setChanged_by($this);
            }
    
            return $this;
        }
    
        public function removeApplication_status_history(Application_status_history $application_status_history): self
        {
            if ($this->application_status_historys->removeElement($application_status_history)) {
                // set the owning side to null (unless already changed)
                if ($application_status_history->getChanged_by() === $this) {
                    $application_status_history->setChanged_by(null);
                }
            }
    
            return $this;
        }
}
