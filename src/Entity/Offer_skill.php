<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Job_offer;

#[ORM\Entity]
class Offer_skill
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

        #[ORM\ManyToOne(targetEntity: Job_offer::class, inversedBy: "offer_skills")]
    #[ORM\JoinColumn(name: 'offer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Job_offer $offer_id;

    #[ORM\Column(type: "string", length: 100)]
    private string $skill_name;

    #[ORM\Column(type: "string")]
    private string $level_required;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getOffer_id()
    {
        return $this->offer_id;
    }

    public function setOffer_id($value)
    {
        $this->offer_id = $value;
    }

    public function getSkill_name()
    {
        return $this->skill_name;
    }

    public function setSkill_name($value)
    {
        $this->skill_name = $value;
    }

    public function getLevel_required()
    {
        return $this->level_required;
    }

    public function setLevel_required($value)
    {
        $this->level_required = $value;
    }
}
