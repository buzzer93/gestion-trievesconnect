<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Association;
use App\Entity\PrintMunicipalConsumption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrintMunicipalConsumption>
 */
class PrintMunicipalConsumptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrintMunicipalConsumption::class);
    }

    /**
     * Impressions payées par le crédit mairie d'une association sur un
     * trimestre donné, pour la page admin de facturation. Le regroupement
     * par type (couleur/format) et le total se calculent côté PHP à
     * l'affichage : peu de lignes par trimestre, pas besoin d'agrégation
     * SQL (cf. règles projet anti-surengineering).
     *
     * @return PrintMunicipalConsumption[]
     */
    public function findForQuarter(Association $association, int $year, int $quarter): array
    {
        [$start, $end] = self::quarterBounds($year, $quarter);

        return $this->createQueryBuilder('c')
            ->andWhere('c.association = :association')
            ->andWhere('c.createdAt >= :start')
            ->andWhere('c.createdAt < :end')
            ->setParameter('association', $association)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.createdAt', 'ASC')
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
