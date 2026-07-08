<?php

namespace App\Repository;

use App\Entity\Quote;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /** @return Quote[] newest first */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['issuedAt' => 'DESC', 'id' => 'DESC']);
    }

    /** Next correlative number for a user's quote series (starts at 1). */
    public function nextNumber(User $user, string $series): int
    {
        $max = $this->createQueryBuilder('q')
            ->select('MAX(q.number)')
            ->andWhere('q.user = :u')->setParameter('u', $user)
            ->andWhere('q.series = :s')->setParameter('s', $series)
            ->getQuery()->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
