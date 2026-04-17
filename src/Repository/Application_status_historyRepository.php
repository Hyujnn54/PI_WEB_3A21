<?php

namespace App\Repository;

use App\Entity\Application_status_history;
use App\Entity\Job_application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Application_status_historyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application_status_history::class);
    }

    /**
     * @return Application_status_history[]
     */
    public function findForApplication(Job_application $application): array
    {
        return $this->findBy(['application_id' => $application], ['changed_at' => 'DESC']);
    }

    public function findForApplicationById(Job_application $application, int $historyId): ?Application_status_history
    {
        return $this->findOneBy(['id' => $historyId, 'application_id' => $application]);
    }
}