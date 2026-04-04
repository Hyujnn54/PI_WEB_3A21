<?php

namespace App\Repository;

use App\Entity\Offer_skill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Offer_skillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer_skill::class);
    }

    // Add custom methods as needed
}