<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /** @return Invoice[] newest first */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['issuedAt' => 'DESC', 'id' => 'DESC']);
    }

    /** Next correlative number for a user's series (starts at 1). */
    public function nextNumber(User $user, string $series): int
    {
        $max = $this->createQueryBuilder('i')
            ->select('MAX(i.number)')
            ->andWhere('i.user = :u')->setParameter('u', $user)
            ->andWhere('i.series = :s')->setParameter('s', $series)
            ->getQuery()->getSingleScalarResult();

        return ((int) $max) + 1;
    }

    /** The most recently issued invoice for a user (for the Verifactu hash chain). */
    public function lastForUser(User $user): ?Invoice
    {
        return $this->findOneBy(['user' => $user], ['id' => 'DESC']);
    }
}
