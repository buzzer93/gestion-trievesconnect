<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Association;
use App\Entity\PrintTransaction;
use App\Entity\PrintTransactionLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrintTransactionLine>
 */
class PrintTransactionLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrintTransactionLine::class);
    }

    /**
     * Historique complet d'un bénéficiaire (client ou association), toutes
     * sources confondues -- même rôle que
     * PrintMunicipalConsumptionRepository::findAllForAssociation() côté
     * ancienne table.
     *
     * @return PrintTransactionLine[]
     */
    public function findAllForCustomer(\App\Entity\Customer $customer): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('t')
            ->join('l.transaction', 't')
            ->andWhere('t.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Lignes financées par le crédit mairie d'une association sur un
     * trimestre donné -- même rôle que
     * PrintMunicipalConsumptionRepository::findForQuarter().
     *
     * @return PrintTransactionLine[]
     */
    public function findMunicipalForQuarter(Association $association, int $year, int $quarter): array
    {
        [$start, $end] = self::quarterBounds($year, $quarter);

        return $this->createQueryBuilder('l')
            ->addSelect('t')
            ->join('l.transaction', 't')
            ->andWhere('t.customer = :association')
            ->andWhere('l.fundingSource = :fundingSource')
            ->andWhere('t.createdAt >= :start')
            ->andWhere('t.createdAt < :end')
            ->setParameter('association', $association)
            ->setParameter('fundingSource', PrintTransaction::FUNDING_MUNICIPAL)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Lignes financées par le crédit mairie de TOUTES les associations sur
     * un trimestre donné -- pour le récap "Budget mairie".
     *
     * @return PrintTransactionLine[]
     */
    public function findMunicipalForQuarterAllAssociations(int $year, int $quarter): array
    {
        [$start, $end] = self::quarterBounds($year, $quarter);

        return $this->createQueryBuilder('l')
            ->addSelect('t')
            ->addSelect('c')
            ->join('l.transaction', 't')
            ->join('t.customer', 'c')
            ->andWhere('l.fundingSource = :fundingSource')
            ->andWhere('t.createdAt >= :start')
            ->andWhere('t.createdAt < :end')
            ->setParameter('fundingSource', PrintTransaction::FUNDING_MUNICIPAL)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} Bornes [début, fin[ du trimestre
     */
    public static function quarterBounds(int $year, int $quarter): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));

        return [$start, $start->modify('+3 months')];
    }
}
