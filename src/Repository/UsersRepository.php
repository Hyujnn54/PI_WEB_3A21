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
public function findBySearchAndRole(?string $search, ?string $role)
{
    $qb = $this->createQueryBuilder('u');

    if ($search) {
        $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }

    if ($role) {
        $qb->andWhere('u.roles LIKE :role')
           ->setParameter('role', '%' . $role . '%');
    }

    return $qb->getQuery()->getResult();
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