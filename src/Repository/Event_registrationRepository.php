<?php

namespace App\Repository;

use App\Entity\Event_registration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event_registration>
 */
class Event_registrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event_registration::class);
    }

    // Add custom methods as needed
}