<?php

namespace App\Repository;

use App\Entity\Job_offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Job_offerRepository extends ServiceEntityRepository
{
    private const SKILL_LEVEL_RANKS = [
        'beginner' => 1,
        'intermediate' => 2,
        'advanced' => 3,
    ];

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

    /**
     * @param array<int, array<string, mixed>> $offers
     * @return array<string, array<string, mixed>>
     */
    public function buildCandidateOfferMatchData(?string $candidateId, array $offers): array
    {
        $candidateId = trim((string) $candidateId);
        if ($candidateId === '' || count($offers) === 0) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $candidateSkills = $this->fetchCandidateSkillMap($connection, $candidateId);

        $matchData = [];
        foreach ($offers as $offer) {
            $offerId = trim((string) ($offer['id'] ?? ''));
            if ($offerId === '') {
                continue;
            }

            $offerSkills = $this->fetchOfferSkillRows($connection, $offerId);
            $matchData[$offerId] = $this->buildMatchResult($candidateSkills, $offerSkills);
        }

        return $matchData;
    }

    /**
     * @param object $connection
     * @return array<string, array{skill_name:string, level:string, rank:int}>
     */
    private function fetchCandidateSkillMap($connection, string $candidateId): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT skill_name, level FROM candidate_skill WHERE candidate_id = :candidate_id ORDER BY id ASC',
            ['candidate_id' => $candidateId]
        );

        $skills = [];
        foreach ($rows as $row) {
            $skillName = trim((string) ($row['skill_name'] ?? ''));
            if ($skillName === '') {
                continue;
            }

            $normalizedSkill = $this->normalizeSkillName($skillName);
            if ($normalizedSkill === '') {
                continue;
            }

            $level = strtolower(trim((string) ($row['level'] ?? '')));
            $skills[$normalizedSkill] = [
                'skill_name' => $skillName,
                'level' => $level,
                'rank' => $this->skillRank($level),
            ];
        }

        return $skills;
    }

    /**
     * @param object $connection
     * @return array<int, array<string, string>>
     */
    private function fetchOfferSkillRows($connection, string $offerId): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT skill_name, level_required FROM offer_skill WHERE offer_id = :offer_id ORDER BY id ASC',
            ['offer_id' => $offerId]
        );

        $skills = [];
        foreach ($rows as $row) {
            $skillName = trim((string) ($row['skill_name'] ?? ''));
            if ($skillName === '') {
                continue;
            }

            $skills[] = [
                'skill_name' => $skillName,
                'level_required' => strtolower(trim((string) ($row['level_required'] ?? ''))),
            ];
        }

        return $skills;
    }

    /**
     * @param array<string, array{skill_name:string, level:string, rank:int}> $candidateSkills
     * @param array<int, array<string, string>> $offerSkills
     * @return array<string, mixed>
     */
    private function buildMatchResult(array $candidateSkills, array $offerSkills): array
    {
        $matchingSkills = [];
        $missingSkills = [];
        $matchingScore = 0.0;
        $requiredSkillCount = 0;
        $exactMatchCount = 0;
        $partialMatchCount = 0;

        foreach ($offerSkills as $offerSkill) {
            $requiredName = trim((string) ($offerSkill['skill_name'] ?? ''));
            if ($requiredName === '') {
                continue;
            }

            $requiredSkillCount += 1;
            $requiredLevel = strtolower(trim((string) ($offerSkill['level_required'] ?? '')));
            $requiredRank = max(1, $this->skillRank($requiredLevel));
            $candidateSkill = $candidateSkills[$this->normalizeSkillName($requiredName)] ?? null;

            if ($candidateSkill === null) {
                $missingSkills[] = sprintf('%s (niveau requis : %s)', $requiredName, $this->formatSkillLevel($requiredLevel));
                continue;
            }

            $candidateRank = max(0, (int) ($candidateSkill['rank'] ?? 0));
            $candidateLevel = (string) ($candidateSkill['level'] ?? '');
            $fitRatio = min($candidateRank, $requiredRank) / $requiredRank;
            $matchingScore += $fitRatio;

            if ($candidateRank >= $requiredRank) {
                $exactMatchCount += 1;
                $matchingSkills[] = sprintf(
                    '%s (%s >= %s)',
                    $requiredName,
                    $this->formatSkillLevel($candidateLevel),
                    $this->formatSkillLevel($requiredLevel)
                );
            } else {
                $partialMatchCount += 1;
                $matchingSkills[] = sprintf(
                    '%s (%s < %s)',
                    $requiredName,
                    $this->formatSkillLevel($candidateLevel),
                    $this->formatSkillLevel($requiredLevel)
                );
            }
        }

        $score = $requiredSkillCount > 0 ? (int) round(($matchingScore / $requiredSkillCount) * 100) : 100;
        $score = max(0, min(100, $score));
        $label = $this->resolveMatchLabel($score);
        $details = [
            'required_skill_count' => $requiredSkillCount,
            'matching_skill_count' => count($matchingSkills),
            'exact_match_count' => $exactMatchCount,
            'partial_match_count' => $partialMatchCount,
            'missing_skill_count' => count($missingSkills),
            'label' => $label,
        ];

        $explanation = $this->buildMatchExplanation($score, $details, $matchingSkills, $missingSkills);

        return [
            'score' => $score,
            'details' => $details,
            'matching_skills' => $matchingSkills,
            'missing_skills' => $missingSkills,
            'explanation' => $explanation,
            'label' => $label,
        ];
    }

    private function buildMatchExplanation(int $score, array $details, array $matchingSkills, array $missingSkills): string
    {
        $requiredSkillCount = (int) ($details['required_skill_count'] ?? 0);
        $matchingSkillCount = (int) ($details['matching_skill_count'] ?? 0);
        $partialMatchCount = (int) ($details['partial_match_count'] ?? 0);
        $missingSkillCount = (int) ($details['missing_skill_count'] ?? 0);
        $label = (string) ($details['label'] ?? 'matching');

        if ($requiredSkillCount === 0) {
            return 'Aucune compétence requise n’est définie pour cette offre. Le score est donc neutre et le matching reste favorable.';
        }

        $exactMatchText = ((int) ($details['exact_match_count'] ?? 0)) === 1 ? 'correspondance complète' : 'correspondances complètes';
        $partialMatchText = $partialMatchCount === 1 ? 'correspondance partielle' : 'correspondances partielles';
        $missingMatchText = $missingSkillCount === 1 ? 'compétence manquante' : 'compétences manquantes';

        $summary = sprintf(
            'Cette offre demande %d compétence%s. Vous avez %d %s, %d %s et %d %s.',
            $requiredSkillCount,
            $requiredSkillCount > 1 ? 's' : '',
            (int) ($details['exact_match_count'] ?? 0),
            $exactMatchText,
            $partialMatchCount,
            $partialMatchText,
            $missingSkillCount,
            $missingMatchText
        );

        $strengthText = count($matchingSkills) > 0
            ? 'Points forts : ' . implode(', ', array_slice($matchingSkills, 0, 4)) . '.'
            : 'Aucune compétence n’est encore alignée avec les exigences principales.';

        $gapText = count($missingSkills) > 0
            ? 'Compétences à renforcer : ' . implode(', ', array_slice($missingSkills, 0, 4)) . '.'
            : 'Aucun manque bloquant n’a été détecté sur les compétences listées.';

        return sprintf(
            '%s %s %s Le score global de %d %% correspond à un matching %s.',
            $summary,
            $strengthText,
            $gapText,
            $score,
            $label
        );
    }

    private function normalizeSkillName(string $skillName): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $skillName)));
    }

    private function skillRank(string $level): int
    {
        return self::SKILL_LEVEL_RANKS[strtolower(trim($level))] ?? 0;
    }

    private function formatSkillLevel(string $level): string
    {
        $normalized = $this->normalizeSkillLevel($level);

        return $normalized === 'non précisé' ? $normalized : ucfirst($normalized);
    }

    private function normalizeSkillLevel(string $level): string
    {
        $normalized = strtolower(trim($level));

        return match ($normalized) {
            'beginner' => 'beginner',
            'intermediate' => 'intermediate',
            'advanced' => 'advanced',
            default => $normalized !== '' ? $normalized : 'non précisé',
        };
    }

    private function resolveMatchLabel(int $score): string
    {
        if ($score >= 85) {
            return 'excellent';
        }

        if ($score >= 70) {
            return 'bon';
        }

        if ($score >= 50) {
            return 'moyen';
        }

        return 'faible';
    }
}