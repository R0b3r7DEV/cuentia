<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository = the place where database queries for Transaction live.
 * ES: El repositorio = el lugar donde viven las consultas a la BD de Transaction.
 *
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /** All of a user's transactions, newest first. / Todos los movimientos de un usuario, recientes primero. */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['bookedAt' => 'DESC', 'id' => 'DESC']);
    }
}
