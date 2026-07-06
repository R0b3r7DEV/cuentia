<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /** @return Customer[] */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['name' => 'ASC']);
    }
}
