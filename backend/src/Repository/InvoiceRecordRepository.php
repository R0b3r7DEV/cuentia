<?php

namespace App\Repository;

use App\Entity\InvoiceRecord;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceRecord>
 */
class InvoiceRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceRecord::class);
    }

    /** The last record in a user's chain — its hash becomes the next record's previousHash. */
    public function lastForUser(User $user): ?InvoiceRecord
    {
        return $this->findOneBy(['user' => $user], ['id' => 'DESC']);
    }

    /** @return InvoiceRecord[] the whole chain, oldest first (for verification). */
    public function chainForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['id' => 'ASC']);
    }
}
