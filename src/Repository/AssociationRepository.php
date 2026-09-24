<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Association;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Association>
 *
 * Le débit pour impression (mairie en priorité, bascule sur le personnel)
 * a été déplacé dans PrintPolicyEvaluator + PrintTransactionRepository
 * (cf. décision du 2026-08-25) -- ce repository ne fait plus que de la
 * lecture, le débit associatif n'a plus besoin de logique propre ici.
 */
class AssociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Association::class);
    }

    /**
     * @return Association[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
