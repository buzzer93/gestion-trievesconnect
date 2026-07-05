<?php

namespace App\Repository;

use App\Entity\Customer;
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
    public function findOneByPhoneNumber($value): ?Customer
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.phoneNumber = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Débite le solde d'un client suite à une impression PrintGate
     * autorisée. Persistance dans le repository (comme
     * PrintGateUsedTokenRepository::markAsUsed()) plutôt que dans
     * PrintPolicyEvaluator, qui n'a pas d'autre raison de dépendre de
     * l'EntityManager.
     */
    public function debitBalance(Customer $customer, int $cents): void
    {
        $customer->removeBalanceCents($cents);
        $this->getEntityManager()->flush();
    }
    
    /**
     * @return Customer[] Returns an array of Customer objects
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }


}
