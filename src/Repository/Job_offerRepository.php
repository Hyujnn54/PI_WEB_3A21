<?php

namespace App\Repository;

use App\Entity\Job_offer;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Job_offer>
 */
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

    public function createPortalOffersFilterQueryBuilder(string $role, ?string $recruiterId): QueryBuilder
    {
        $qb = $this->createQueryBuilder('jo')
            ->select(
                'jo.id AS id',
                'IDENTITY(jo.recruiter_id) AS recruiter_id',
                'jo.title AS title',
                'jo.description AS description',
                'jo.location AS location',
                'jo.latitude AS latitude',
                'jo.longitude AS longitude',
                'jo.contract_type AS contract_type',
                'jo.status AS status',
                'jo.deadline AS deadline',
                'jo.created_at AS created_at'
            )
            ->orderBy('jo.created_at', 'DESC');

        if ($role === 'recruiter') {
            if (trim((string) $recruiterId) === '') {
                $qb->andWhere('1 = 0');
            } else {
                $qb
                    ->andWhere('IDENTITY(jo.recruiter_id) = :recruiter_id')
                    ->setParameter('recruiter_id', (string) $recruiterId);
            }
        }

        return $qb;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPortalOffersFromQueryBuilder(QueryBuilder $qb, int $limit = 25): array
    {
        return $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function createAdminOffersFilterQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('jo')
            ->select(
                'jo.id AS id',
                'IDENTITY(jo.recruiter_id) AS recruiter_id',
                'jo.title AS title',
                'jo.location AS location',
                'jo.contract_type AS contract_type',
                'jo.status AS status',
                'jo.created_at AS created_at',
                'jo.deadline AS deadline'
            )
            ->orderBy('jo.created_at', 'DESC');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAdminOffersFromQueryBuilder(QueryBuilder $qb, int $limit = 300): array
    {
        $offers = $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        if (count($offers) === 0) {
            return [];
        }

        $offerIds = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['id'] ?? '')),
            $offers
        )));

        if (count($offerIds) === 0) {
            return $offers;
        }

        $connection = $this->getEntityManager()->getConnection();

        $warningRows = $connection->fetchAllAssociative(
            <<<'SQL'
SELECT jw.job_offer_id, jw.status AS warning_status, jw.reason AS warning_reason
FROM job_offer_warning jw
INNER JOIN (
    SELECT job_offer_id, MAX(created_at) AS max_created_at
    FROM job_offer_warning
    WHERE status IN ('SENT', 'RESOLVED')
      AND job_offer_id IN (:offer_ids)
    GROUP BY job_offer_id
) latest
    ON latest.job_offer_id = jw.job_offer_id
   AND latest.max_created_at = jw.created_at
WHERE jw.status IN ('SENT', 'RESOLVED')
SQL,
            ['offer_ids' => $offerIds],
            ['offer_ids' => ArrayParameterType::STRING]
        );

        $warningMap = [];
        foreach ($warningRows as $warningRow) {
            $warningMap[(string) ($warningRow['job_offer_id'] ?? '')] = [
                'warning_status' => $warningRow['warning_status'] ?? null,
                'warning_reason' => $warningRow['warning_reason'] ?? null,
            ];
        }

        foreach ($offers as &$offer) {
            $warning = $warningMap[(string) ($offer['id'] ?? '')] ?? null;
            $offer['warning_status'] = $warning['warning_status'] ?? null;
            $offer['warning_reason'] = $warning['warning_reason'] ?? null;
        }

        return $offers;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAdminOfferStats(int $limit = 1000): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $rows = $connection->fetchAllAssociative(
            'SELECT id, recruiter_id, title, location, contract_type, status, deadline FROM job_offer ORDER BY created_at DESC LIMIT ' . (int) $limit
        );

        return $this->buildOfferStatsFromRows($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $offers
     * @return array<string, mixed>
     */
    private function buildOfferStatsFromRows(array $offers): array
    {
        $totalPublished = count($offers);
        $totalClosed = 0;
        $totalOpen = 0;
        $cityStats = [];
        $contractStats = [];

        foreach ($offers as $offer) {
            $city = trim((string) ($offer['location'] ?? 'Unknown'));
            if ($city === '') {
                $city = 'Unknown';
            }

            $contractType = trim((string) ($offer['contract_type'] ?? 'Unknown'));
            if ($contractType === '') {
                $contractType = 'Unknown';
            }

            $status = strtolower(trim((string) ($offer['status'] ?? 'open')));
            $isClosed = $status === 'closed';
            $isOpen = $status === 'open';

            if ($isClosed) {
                $totalClosed += 1;
            }
            if ($isOpen) {
                $totalOpen += 1;
            }

            if (!isset($cityStats[$city])) {
                $cityStats[$city] = ['city' => $city, 'total' => 0, 'open' => 0, 'closed' => 0];
            }
            $cityStats[$city]['total'] += 1;
            if ($isOpen) {
                $cityStats[$city]['open'] += 1;
            }
            if ($isClosed) {
                $cityStats[$city]['closed'] += 1;
            }

            if (!isset($contractStats[$contractType])) {
                $contractStats[$contractType] = ['contract_type' => $contractType, 'total' => 0, 'open' => 0, 'closed' => 0];
            }
            $contractStats[$contractType]['total'] += 1;
            if ($isOpen) {
                $contractStats[$contractType]['open'] += 1;
            }
            if ($isClosed) {
                $contractStats[$contractType]['closed'] += 1;
            }
        }

        $closedPercentage = $totalPublished > 0 ? round(($totalClosed / $totalPublished) * 100, 2) : 0.0;
        $openPercentage = $totalPublished > 0 ? round(($totalOpen / $totalPublished) * 100, 2) : 0.0;

        $cityStatsList = array_values($cityStats);
        foreach ($cityStatsList as &$row) {
            $row['open_rate'] = round(($row['open'] / $row['total']) * 100, 2);
            $row['closed_rate'] = round(($row['closed'] / $row['total']) * 100, 2);
        }

        $contractStatsList = array_values($contractStats);
        foreach ($contractStatsList as &$row) {
            $row['percentage'] = $totalPublished > 0 ? round(($row['total'] / $totalPublished) * 100, 2) : 0.0;
        }

        usort($cityStatsList, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        usort($contractStatsList, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'total_published' => $totalPublished,
            'total_closed' => $totalClosed,
            'total_open' => $totalOpen,
            'closed_percentage' => $closedPercentage,
            'open_percentage' => $openPercentage,
            'city_stats' => $cityStatsList,
            'contract_stats' => $contractStatsList,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRecruiterOfferStats(?string $recruiterId, int $limit = 50): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $rows = $connection->fetchAllAssociative(
            'SELECT id, recruiter_id, title, location, contract_type, status, deadline FROM job_offer WHERE recruiter_id = :recruiter_id ORDER BY created_at DESC LIMIT ' . (int) $limit,
            ['recruiter_id' => $recruiterId]
        );

        return $this->buildOfferStatsFromRows($rows);
    }
}
