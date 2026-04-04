<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Candidate;

#[ORM\Entity]
class Candidate_skill
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Candidate::class, inversedBy: "candidate_skills")]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Candidate $candidate_id;

    #[ORM\Column(type: "string", length: 100)]
    private string $skill_name;

    #[ORM\Column(type: "string")]
    private string $level;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getCandidate_id()
    {
        return $this->candidate_id;
    }

    public function setCandidate_id($value)
    {
        $this->candidate_id = $value;
    }

    public function getSkill_name()
    {
        return $this->skill_name;
    }

    public function setSkill_name($value)
    {
        $this->skill_name = $value;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setLevel($value)
    {
        $this->level = $value;
    }
}
