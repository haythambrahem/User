<?php

namespace App\Repository;

use App\Entity\Personne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\ORM\Query\Expr\Func;
/**
 * @extends ServiceEntityRepository<Personne>
* @implements PasswordUpgraderInterface<Personne>
 *
 * @method Personne|null find($id, $lockMode = null, $lockVersion = null)
 * @method Personne|null findOneBy(array $criteria, array $orderBy = null)
 * @method Personne[]    findAll()
 * @method Personne[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonneRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personne::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Personne) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    public function save(Personne $entity, bool $flush = false)
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function search($searchCriteria): array
{
    $entityManager = $this->getEntityManager();
    $queryBuilder = $entityManager->createQueryBuilder();

    $queryBuilder
        ->select('p')
        ->from(Personne::class, 'p');

    if ($searchCriteria) {
        $queryBuilder
            ->where('p.nom LIKE :searchCriteria OR p.prenom LIKE :searchCriteria OR p.email LIKE :searchCriteria OR p.ign LIKE :searchCriteria')
            ->setParameter('searchCriteria', '%' . $searchCriteria . '%');
    }

    $query = $queryBuilder->getQuery();
    $personnes = $query->getResult();

    return $personnes;
}


public function countVerifiedUsers(): int
{
    return $this->createQueryBuilder('u')
        ->select('COUNT(u.id)')
        ->where('u.isVerified = 1')
        ->getQuery()
        ->getSingleScalarResult();
}
public function countBannedUsers(): int
{
    return $this->createQueryBuilder('u')
        ->select('COUNT(u.id)')
        ->where('u.isBanned = 1')
        ->getQuery()
        ->getSingleScalarResult();
}
public function countTotalUsers(): int
{
    return $this->createQueryBuilder('u')
        ->select('COUNT(u.id)')
        ->getQuery()
        ->getSingleScalarResult();
}

public function findLatestUsers(): array
{
    $qb = $this->createQueryBuilder('u');
    
    return $qb
        ->orderBy('u.id', 'DESC') // Assuming 'id' is the identifier field
        ->setMaxResults(10)
        ->getQuery()
        ->getResult();
}
//    /**
//     * @return Personne[] Returns an array of Personne objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Personne
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
