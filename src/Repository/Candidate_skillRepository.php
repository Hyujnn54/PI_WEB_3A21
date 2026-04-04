<?php

namespace App\Repository;

use App\Entity\Candidate_skill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Candidate_skillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidate_skill::class);
    }

    // Add custom methods as needed
}