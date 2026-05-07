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

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getOffer_id(): Job_offer
    {
        return $this->offer_id;
    }

    public function setOffer_id(Job_offer $value): void
    {
        $this->offer_id = $value;
    }

    public function getSkill_name(): string
    {
        return $this->skill_name;
    }

    public function setSkill_name(string $value): void
    {
        $this->skill_name = $value;
    }

    public function getLevel_required(): string
    {
        return $this->level_required;
    }

    public function setLevel_required(string $value): void
    {
        $this->level_required = $value;
    }
}
