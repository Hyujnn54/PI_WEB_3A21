<?php

namespace App\Repository;

use App\Entity\Job_offer_warning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Job_offer_warning>
 */
class Job_offer_warningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job_offer_warning::class);
    }

    // Add custom methods as needed
}