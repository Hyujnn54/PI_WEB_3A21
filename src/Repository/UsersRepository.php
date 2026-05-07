<?php

namespace App\Repository;

use App\Entity\Admin;
use App\Entity\Candidate;
use App\Entity\Recruiter;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Users>
 */
class UsersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Users::class);
    

    
}
/**
 * @return list<Users>
 */
public function findBySearchAndRole(?string $search = null, ?string $role = null): array
{
    $users = $this->findAll();   // Get all users

    $filtered = [];

    foreach ($users as $user) {
        $match = true;

        // Search filter (name or email)
        if (trim((string) $search) !== '') {
            $searchLower = strtolower((string) $search);
            $fullName = strtolower($user->getFirstName() . ' ' . $user->getLastName());
            $email = strtolower($user->getEmail() ?? '');

            if (strpos($fullName, $searchLower) === false && strpos($email, $searchLower) === false) {
                $match = false;
            }
        }

        // Role filter
        if ($role && $match) {
            $userRoles = $user->getRoles();
            if (!in_array($role, $userRoles, true)) {
                $match = false;
            }
        }

        if ($match) {
            $filtered[] = $user;
        }
    }

    // Optional: sort by first name
    usort($filtered, function(Users $a, Users $b): int {
        return strcmp($a->getFirstName() ?? '', $b->getFirstName() ?? '');
    });

    return $filtered;
}
/**
 * @return list<Users>
 */
public function findByRole(string $role): array
{
    return $this->createQueryBuilder('u')
        ->andWhere('u.roles LIKE :role')
        ->setParameter('role', '%' . $role . '%')
        ->getQuery()
        ->getResult();
}
public function countByRole(string $role): int
{
    return (int) $this->createQueryBuilder('u')
        ->select('count(u.id)')
        ->where('u.roles LIKE :role')
        ->setParameter('role', '%' . $role . '%')
        ->getQuery()
        ->getSingleScalarResult();
}

public function findOneValidByEmail(string $email): ?Users
{
    $row = $this->getEntityManager()->getConnection()->fetchAssociative(
        'SELECT id, discr FROM users WHERE email = :email LIMIT 1',
        ['email' => $email]
    );

    if (!$row) {
        return null;
    }

    $id = (string) ($row['id'] ?? '');
    $discr = strtolower(trim((string) ($row['discr'] ?? '')));

    try {
        $user = match ($discr) {
            'admin' => $this->getEntityManager()->getRepository(Admin::class)->find($id),
            'candidate' => $this->getEntityManager()->getRepository(Candidate::class)->find($id),
            'recruiter' => $this->getEntityManager()->getRepository(Recruiter::class)->find($id),
            default => null,
        };

        if ($user instanceof Users) {
            return $user;
        }

        // Fallback for legacy rows where discriminator exists but subtype row is inconsistent.
        return $this->findOneBy(['email' => $email]);
    } catch (\Throwable) {
        return null;
    }
}


}
