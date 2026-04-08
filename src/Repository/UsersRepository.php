<?php

namespace App\Repository;

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
public function findBySearchAndRole(?string $search = null, ?string $role = null): array
{
    $users = $this->findAll();   // Get all users

    $filtered = [];

    foreach ($users as $user) {
        $match = true;

        // Search filter (name or email)
        if ($search && $search !== '') {
            $searchLower = strtolower($search);
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
    usort($filtered, function($a, $b) {
        return strcmp($a->getFirstName(), $b->getFirstName());
    });

    return $filtered;
}
public function findByRole(string $role)
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


}