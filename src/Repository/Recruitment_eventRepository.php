<?php

namespace App\Repository;

use App\Entity\Recruitment_event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Recruitment_eventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recruitment_event::class);
    }

    // Add custom methods as needed
}