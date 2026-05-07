<?php

namespace App\Repository;

use App\Entity\Warning_correction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Warning_correction>
 */
class Warning_correctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Warning_correction::class);
    }

    // Add custom methods as needed
}