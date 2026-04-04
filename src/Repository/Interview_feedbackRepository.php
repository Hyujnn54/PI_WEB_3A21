<?php

namespace App\Repository;

use App\Entity\Interview_feedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Interview_feedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interview_feedback::class);
    }

    // Add custom methods as needed
}