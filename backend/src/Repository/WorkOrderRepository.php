<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkOrder>
 */
class WorkOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkOrder::class);
    }

    /** @return WorkOrder[] newest first */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** A work order by id, only if it belongs to this user. */
    public function findOwned(int $id, User $user): ?WorkOrder
    {
        $wo = $this->find($id);

        return ($wo !== null && $wo->getUser() === $user) ? $wo : null;
    }
}
