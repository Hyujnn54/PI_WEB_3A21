<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class CandidateOfferMatchingService
{
    private const SKILL_LEVEL_RANKS = [
        'beginner' => 1,
        'intermediate' => 2,
        'advanced' => 3,
    ];

    public function __construct(private readonly Connection $connection)
    {
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

        $candidateSkills = $this->fetchCandidateSkillMap($candidateId);
        $matchData = [];

        foreach ($offers as $offer) {
            $offerId = trim((string) ($offer['id'] ?? ''));
            if ($offerId === '') {
                continue;
            }

            $offerSkills = $this->fetchOfferSkillRows($offerId);
            $matchData[$offerId] = $this->buildMatchResult($candidateSkills, $offerSkills);
        }

        return $matchData;
    }

    /**
     * @return array<string, array{skill_name:string, level:string, rank:int}>
     */
    private function fetchCandidateSkillMap(string $candidateId): array
    {
        $rows = $this->connection->fetchAllAssociative(
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
     * @return array<int, array<string, string>>
     */
    private function fetchOfferSkillRows(string $offerId): array
    {
        $rows = $this->connection->fetchAllAssociative(
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

            $candidateRank = max(0, $candidateSkill['rank']);
            $candidateLevel = $candidateSkill['level'];
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

        return [
            'score' => $score,
            'details' => $details,
            'matching_skills' => $matchingSkills,
            'missing_skills' => $missingSkills,
            'explanation' => $this->buildMatchExplanation($score, $details, $matchingSkills, $missingSkills),
            'label' => $label,
        ];
    }

    private function buildMatchExplanation(int $score, array $details, array $matchingSkills, array $missingSkills): string
    {
        $requiredSkillCount = (int) ($details['required_skill_count'] ?? 0);
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
