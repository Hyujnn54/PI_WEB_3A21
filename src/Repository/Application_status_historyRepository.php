<?php

namespace App\Repository;

use App\Entity\Application_status_history;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Application_status_historyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application_status_history::class);
    }

    // Add custom methods as needed
}