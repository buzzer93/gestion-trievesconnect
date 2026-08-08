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
            ->orderBy('r.colorMode', 'ASC')
            ->addOrderBy('r.paperSize', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByTypeAndFormat(string $colorMode, string $paperSize): ?PrintPriceRate
    {
        return $this->findOneBy(['colorMode' => $colorMode, 'paperSize' => $paperSize]);
    }
}
