<?php

namespace App\Repository;

use App\Entity\Admin;
use App\Entity\Application_status_history;
use App\Entity\Job_application;
use App\Entity\Recruiter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class Job_applicationRepository extends ServiceEntityRepository
{
    private const SORT_MODES = ['default', 'title_asc', 'title_desc', 'date_desc', 'date_asc'];

    private const STATUS_FILTERS = ['submitted', 'in_review', 'shortlisted', 'rejected', 'interview', 'hired'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job_application::class);
    }

    public function archiveById(int $applicationId, ?Admin $admin = null): string
    {
        $application = $this->find($applicationId);
        if (!$application) {
            return 'not_found';
        }

        if ($application->getIs_archived()) {
            return 'already_archived';
        }

        $application->setIs_archived(true);

        if ($admin && $admin->getId()) {
            $history = new Application_status_history();
            $history->setApplication_id($application);
            $history->setStatus('ARCHIVED');
            $history->setChanged_at(new \DateTime());
            $history->setChanged_by($admin);
            $history->setNote('Admin archived this application.');
            $this->getEntityManager()->persist($history);
        }

        $this->getEntityManager()->flush();

        return 'archived';
    }

    public function unarchiveById(int $applicationId, ?Admin $admin = null): string
    {
        $application = $this->find($applicationId);
        if (!$application) {
            return 'not_found';
        }

        if (!$application->getIs_archived()) {
            return 'already_unarchived';
        }

        $application->setIs_archived(false);

        if ($admin && $admin->getId()) {
            $history = new Application_status_history();
            $history->setApplication_id($application);
            $history->setStatus('UNARCHIVED');
            $history->setChanged_at(new \DateTime());
            $history->setChanged_by($admin);
            $history->setNote('Admin unarchived this application.');
            $this->getEntityManager()->persist($history);
        }

        $this->getEntityManager()->flush();

        return 'unarchived';
    }

    /**
     * @return Job_application[]
     */
    public function findForRecruiterListing(Recruiter $recruiter, string $search = '', string $status = 'all', string $sort = 'date_desc'): array
    {
        $normalizedSearch = trim($search);
        $normalizedStatus = strtolower(trim($status));
        $normalizedSort = strtolower(trim($sort));

        $allowedSorts = ['date_desc', 'date_asc', 'title_asc', 'title_desc', 'status_asc', 'status_desc'];
        if (!in_array($normalizedSort, $allowedSorts, true)) {
            $normalizedSort = 'date_desc';
        }

        $qb = $this->createQueryBuilder('application')
            ->leftJoin('application.offer_id', 'offer')
            ->leftJoin('application.candidate_id', 'candidate')
            ->addSelect('offer', 'candidate')
            ->andWhere('offer.recruiter_id = :recruiter')
            ->andWhere('application.is_archived = :isArchived')
            ->setParameter('recruiter', $recruiter)
            ->setParameter('isArchived', false);

        $this->applySearch($qb, $normalizedSearch);

        if ($normalizedStatus !== 'all') {
            $qb
                ->andWhere('UPPER(application.current_status) = :recruiterStatus')
                ->setParameter('recruiterStatus', strtoupper($normalizedStatus));
        }

        $this->applyRecruiterSort($qb, $normalizedSort);

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all applications for admin dashboard with optional search/filter/sort.
     *
     * @return Job_application[]
     */
    public function findForAdminDashboard(string $search = '', string $status = 'all', string $sort = 'default'): array
    {
        $normalizedSearch = trim($search);
        $normalizedStatus = strtolower(trim($status));
        $normalizedSort = strtolower(trim($sort));

        if (!in_array($normalizedStatus, self::STATUS_FILTERS, true)) {
            $normalizedStatus = 'all';
        }

        if (!in_array($normalizedSort, self::SORT_MODES, true)) {
            $normalizedSort = 'default';
        }

        $qb = $this->createQueryBuilder('application')
            ->leftJoin('application.offer_id', 'offer')
            ->leftJoin('application.candidate_id', 'candidate')
            ->addSelect('offer', 'candidate');

        $this->applySearch($qb, $normalizedSearch);
        $this->applyStatusFilter($qb, $normalizedStatus);
        $this->applySort($qb, $normalizedSort);

        return $qb->getQuery()->getResult();
    }

    private function applySearch(QueryBuilder $qb, string $search): void
    {
        if ($search === '') {
            return;
        }

        $needle = '%' . mb_strtolower($search) . '%';

        $qb
            ->andWhere(
                $qb->expr()->orX(
                    'LOWER(offer.title) LIKE :search',
                    'LOWER(candidate.firstName) LIKE :search',
                    'LOWER(candidate.lastName) LIKE :search',
                    'LOWER(application.phone) LIKE :search',
                    'LOWER(application.current_status) LIKE :search'
                )
            )
            ->setParameter('search', $needle);
    }

    private function applyStatusFilter(QueryBuilder $qb, string $status): void
    {
        if ($status === 'all') {
            return;
        }

        $qb
            ->andWhere('LOWER(application.current_status) = :status')
            ->setParameter('status', $status);
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        if ($sort === 'title_asc') {
            $qb->orderBy('offer.title', 'ASC')->addOrderBy('application.applied_at', 'DESC');

            return;
        }

        if ($sort === 'title_desc') {
            $qb->orderBy('offer.title', 'DESC')->addOrderBy('application.applied_at', 'DESC');

            return;
        }

        if ($sort === 'date_asc') {
            $qb->orderBy('application.applied_at', 'ASC');

            return;
        }

        $qb->orderBy('application.applied_at', 'DESC');
    }

    private function applyRecruiterSort(QueryBuilder $qb, string $sort): void
    {
        if ($sort === 'date_asc') {
            $qb->orderBy('application.applied_at', 'ASC');

            return;
        }

        if ($sort === 'title_asc') {
            $qb->orderBy('offer.title', 'ASC')->addOrderBy('application.applied_at', 'DESC');

            return;
        }

        if ($sort === 'title_desc') {
            $qb->orderBy('offer.title', 'DESC')->addOrderBy('application.applied_at', 'DESC');

            return;
        }

        if ($sort === 'status_asc') {
            $qb->orderBy('application.current_status', 'ASC')->addOrderBy('application.applied_at', 'DESC');

            return;
        }

        if ($sort === 'status_desc') {
            $qb->orderBy('application.current_status', 'DESC')->addOrderBy('application.applied_at', 'DESC');

            return;
        }

        $qb->orderBy('application.applied_at', 'DESC');
    }
}