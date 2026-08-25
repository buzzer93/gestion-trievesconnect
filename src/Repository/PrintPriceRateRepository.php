<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PrintPriceRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrintPriceRate>
 */
class PrintPriceRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrintPriceRate::class);
    }

    /**
     * @return PrintPriceRate[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.scope', 'ASC')
            ->addOrderBy('r.colorMode', 'ASC')
            ->addOrderBy('r.paperSize', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return PrintPriceRate[]
     */
    public function findAllByScope(string $scope): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.scope = :scope')
            ->setParameter('scope', $scope)
            ->orderBy('r.colorMode', 'ASC')
            ->addOrderBy('r.paperSize', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Ne renvoie une ligne que si elle existe ET est activée -- pour
     * scope=MUNICIPAL, c'est ce qui fait office de règle d'éligibilité
     * (cf. PrintPriceRate::$enabled).
     */
    public function findOneEnabledByScopeAndFormat(string $scope, string $colorMode, string $paperSize): ?PrintPriceRate
    {
        return $this->findOneBy([
            'scope' => $scope,
            'colorMode' => $colorMode,
            'paperSize' => $paperSize,
            'enabled' => true,
        ]);
    }
}
