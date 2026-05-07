<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Candidate;

#[ORM\Entity]
class Candidate_skill
{
    #[ORM\Id]
    #[ORM\GeneratedValue] // FIX 1: This tells MySQL to auto-increment the ID
    #[ORM\Column(type: "integer")] // FIX 2: Use integer instead of string for ID
    private ?int $id = null;

    // FIX 3: Renamed property to $candidate (it represents the WHOLE object, not just the ID)
    #[ORM\ManyToOne(targetEntity: Candidate::class, inversedBy: "candidate_skills")]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Candidate $candidate = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $skill_name;

    #[ORM\Column(type: "string", length: 255)]
    private string $level;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCandidate(): ?Candidate
    {
        return $this->candidate;
    }

    public function setCandidate(?Candidate $candidate): self
    {
        $this->candidate = $candidate;
        return $this;
    }

    public function getSkillName(): ?string
    {
        return $this->skill_name;
    }

public function setSkillName(string $skill_name): self
{
    $this->skill_name = $skill_name;
    return $this;
}
    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = $level;
        return $this;
    }


}
