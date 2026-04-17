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
     * @return array<int, array<string, mixed>>
     */
    public function findOfferRowsForPortal(
        string $role,
        ?string $recruiterId,
        ?string $searchQuery,
        ?string $contractType,
        ?string $status,
        ?string $deadline,
        int $limit = 25
    ): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = 'SELECT id, recruiter_id, title, description, location, contract_type, status, deadline FROM job_offer';
        $where = [];
        $params = [];

        if ($role === 'recruiter') {
            $where[] = 'recruiter_id = :recruiter_id';
            $params['recruiter_id'] = $recruiterId;
        }

        $trimmedSearch = trim((string) $searchQuery);
        if ($trimmedSearch !== '' && $role !== 'admin') {
            $where[] = '(LOWER(title) LIKE :search OR LOWER(description) LIKE :search OR LOWER(location) LIKE :search OR LOWER(contract_type) LIKE :search OR LOWER(status) LIKE :search)';
            $params['search'] = '%' . strtolower($trimmedSearch) . '%';
        }

        $trimmedContractType = trim((string) $contractType);
        if ($trimmedContractType !== '' && $role !== 'admin') {
            $where[] = 'contract_type = :contract_type';
            $params['contract_type'] = $trimmedContractType;
        }

        $trimmedStatus = trim((string) $status);
        if ($trimmedStatus !== '' && $role !== 'admin') {
            $where[] = 'status = :status';
            $params['status'] = $trimmedStatus;
        }

        $trimmedDeadline = trim((string) $deadline);
        if ($trimmedDeadline !== '' && $role !== 'admin') {
            $where[] = 'DATE(deadline) = :deadline';
            $params['deadline'] = $trimmedDeadline;
        }

        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;

        return $connection->fetchAllAssociative($sql, $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAdminOffersForListing(
        ?string $searchQuery,
        ?string $contractType,
        ?string $status,
        ?string $deadline,
        int $limit = 300
    ): array {
        $connection = $this->getEntityManager()->getConnection();
        $where = [];
        $params = [];

        $trimmedSearch = trim((string) $searchQuery);
        if ($trimmedSearch !== '') {
            $where[] = '(LOWER(jo.title) LIKE :search OR LOWER(jo.location) LIKE :search OR LOWER(jo.contract_type) LIKE :search OR LOWER(jo.status) LIKE :search)';
            $params['search'] = '%' . strtolower($trimmedSearch) . '%';
        }

        $trimmedContractType = trim((string) $contractType);
        if ($trimmedContractType !== '') {
            $where[] = 'jo.contract_type = :contract_type';
            $params['contract_type'] = $trimmedContractType;
        }

        $trimmedStatus = trim((string) $status);
        if ($trimmedStatus !== '') {
            $where[] = 'jo.status = :status';
            $params['status'] = $trimmedStatus;
        }

        $trimmedDeadline = trim((string) $deadline);
        if ($trimmedDeadline !== '') {
            $where[] = 'DATE(jo.deadline) = :deadline';
            $params['deadline'] = $trimmedDeadline;
        }

        $sql = <<<'SQL'
SELECT jo.id, jo.recruiter_id, jo.title, jo.location, jo.contract_type, jo.status, jo.created_at, jo.deadline,
       COALESCE(jw.status, NULL) AS warning_status,
       jw.reason AS warning_reason
FROM job_offer jo
LEFT JOIN job_offer_warning jw
  ON jw.job_offer_id = jo.id
 AND jw.status IN ('SENT', 'RESOLVED')
 AND jw.created_at = (
       SELECT MAX(w2.created_at)
       FROM job_offer_warning w2
       WHERE w2.job_offer_id = jo.id
         AND w2.status IN ('SENT', 'RESOLVED')
 )
SQL;

        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY jo.created_at DESC LIMIT ' . (int) $limit;

        try {
            return $connection->fetchAllAssociative($sql, $params);
        } catch (\Throwable) {
            $fallbackSql = 'SELECT jo.id, jo.recruiter_id, jo.title, jo.location, jo.contract_type, jo.status, jo.created_at, jo.deadline, NULL AS warning_status, NULL AS warning_reason FROM job_offer jo';
            if (count($where) > 0) {
                $fallbackSql .= ' WHERE ' . implode(' AND ', $where);
            }
            $fallbackSql .= ' ORDER BY jo.created_at DESC LIMIT ' . (int) $limit;

            return $connection->fetchAllAssociative($fallbackSql, $params);
        }
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
            $row['open_rate'] = $row['total'] > 0 ? round(($row['open'] / $row['total']) * 100, 2) : 0.0;
            $row['closed_rate'] = $row['total'] > 0 ? round(($row['closed'] / $row['total']) * 100, 2) : 0.0;
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