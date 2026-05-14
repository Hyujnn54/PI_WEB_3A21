<?php

namespace App\Repository;

use App\Entity\Interview;
use App\Entity\Candidate;
use App\Entity\Recruiter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Interview>
 */
class InterviewRepository extends ServiceEntityRepository
{
    private const ALLOWED_CRITERIA = ['all', 'title', 'meta', 'description', 'status'];
    private const ALLOWED_SORTS = [
        'default',
        'date_desc',
        'date_asc',
        'status_asc',
        'status_desc',
        'title_asc',
        'title_desc',
        'meta_asc',
        'meta_desc',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interview::class);
    }

    /**
     * @return Interview[]
     */
    public function findBySearchFilterSort(
        string $search = '',
        string $criteria = 'all',
        string $sort = 'date_desc',
        ?Candidate $candidate = null,
        ?Recruiter $recruiter = null,
    ): array
    {
        $normalizedCriteria = in_array($criteria, self::ALLOWED_CRITERIA, true) ? $criteria : 'all';
        $normalizedSort = in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'date_desc';

        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.application_id', 'a')
            ->leftJoin('a.offer_id', 'o')
            ->addSelect('a', 'o');

        if ($candidate instanceof Candidate) {
            $qb
                ->andWhere('a.candidate_id = :candidate')
                ->setParameter('candidate', $candidate);
        }

        if ($recruiter instanceof Recruiter) {
            $qb
                ->andWhere('i.recruiter_id = :recruiter')
                ->setParameter('recruiter', $recruiter);
        }

        $search = trim($search);
        if ($search !== '') {
            $term = '%' . mb_strtolower($search) . '%';
            switch ($normalizedCriteria) {
                case 'title':
                    $qb->andWhere('LOWER(o.title) LIKE :term');
                    break;
                case 'description':
                    $qb->andWhere('LOWER(i.notes) LIKE :term');
                    break;
                case 'status':
                    $qb->andWhere('LOWER(i.status) LIKE :term');
                    break;
                case 'meta':
                    $qb
                        ->andWhere('LOWER(i.mode) LIKE :term OR LOWER(i.location) LIKE :term OR LOWER(i.meeting_link) LIKE :term OR LOWER(i.status) LIKE :term');
                    break;
                case 'all':
                default:
                    $qb
                        ->andWhere('LOWER(o.title) LIKE :term OR LOWER(i.notes) LIKE :term OR LOWER(i.status) LIKE :term OR LOWER(i.mode) LIKE :term OR LOWER(i.location) LIKE :term OR LOWER(i.meeting_link) LIKE :term');
                    break;
            }

            $qb->setParameter('term', $term);
        }

        switch ($normalizedSort) {
            case 'date_asc':
                $qb->orderBy('i.scheduled_at', 'ASC')->addOrderBy('i.id', 'ASC');
                break;
            case 'status_asc':
                $qb->orderBy('i.status', 'ASC')->addOrderBy('i.scheduled_at', 'DESC');
                break;
            case 'status_desc':
                $qb->orderBy('i.status', 'DESC')->addOrderBy('i.scheduled_at', 'DESC');
                break;
            case 'title_asc':
                $qb->orderBy('o.title', 'ASC')->addOrderBy('i.scheduled_at', 'DESC');
                break;
            case 'title_desc':
                $qb->orderBy('o.title', 'DESC')->addOrderBy('i.scheduled_at', 'DESC');
                break;
            case 'meta_asc':
                $qb->orderBy('i.mode', 'ASC')->addOrderBy('i.scheduled_at', 'ASC');
                break;
            case 'meta_desc':
                $qb->orderBy('i.mode', 'DESC')->addOrderBy('i.scheduled_at', 'DESC');
                break;
            case 'default':
            case 'date_desc':
            default:
                $qb->orderBy('i.scheduled_at', 'DESC')->addOrderBy('i.id', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }
}
