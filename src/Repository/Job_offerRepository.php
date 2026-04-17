<?php

namespace App\Repository;

use App\Entity\Job_offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Job_offerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job_offer::class);
    }

    /**
     * @return Job_offer[]
     */
    public function findAllOrderedByCreatedAtDesc(): array
    {
        return $this->findBy([], ['created_at' => 'DESC']);
    }
}