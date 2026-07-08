<?php

namespace App\Repository;

use App\Entity\Installation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Installation>
 */
class InstallationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Installation::class);
    }

    /** @return Installation[] newest first */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['id' => 'DESC']);
    }
}
