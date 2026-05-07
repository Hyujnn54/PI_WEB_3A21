<?php

namespace App\Repository;

use App\Entity\Recruiter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recruiter>
 */
class RecruiterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recruiter::class);
    }

    // Add custom methods as needed
}