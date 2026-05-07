<?php

namespace App\Repository;

use App\Entity\Candidate;
use App\Entity\Candidate_skill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidate_skill>
 */
class Candidate_skillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidate_skill::class);
    }

    /**
     * @return string[]
     */
    public function findSkillSummariesForCandidate(Candidate $candidate): array
    {
        $skills = $this->findBy(['candidate' => $candidate], ['skill_name' => 'ASC']);
        $summaries = [];

        foreach ($skills as $skill) {
            $name = trim((string) $skill->getSkillName());
            if ($name === '') {
                continue;
            }

            $level = trim((string) $skill->getLevel());
            $summaries[] = $level === '' ? $name : ($name . ' (' . $level . ')');
        }

        return $summaries;
    }
}