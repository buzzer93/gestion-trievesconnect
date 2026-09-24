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
     * Trimestre SCOLAIRE (pas calendaire), décision du 2026-08-26 : 4
     * trimestres de 3 mois comme avant, mais calés sur la rentrée plutôt
     * que sur le 1er janvier :
     *   T1 = Septembre-Novembre, T2 = Décembre-Février,
     *   T3 = Mars-Mai, T4 = Juin-Août.
     * $year désigne l'année de DÉBUT de l'année scolaire (ex. year=2026
     * pour l'année scolaire 2026-2027) -- pas l'année civile de chaque
     * trimestre, puisque T2 chevauche le 31/12 (décembre de $year,
     * janvier et février de $year+1). Mois fixes chaque année, pas les
     * dates officielles Éducation Nationale.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} Bornes [début, fin[ du trimestre
     */
    public static function quarterBounds(int $year, int $quarter): array
    {
        $start = (new \DateTimeImmutable(sprintf('%04d-09-01', $year)))
            ->modify(sprintf('+%d months', ($quarter - 1) * 3));

        return [$start, $start->modify('+3 months')];
    }

    /**
     * Trimestre scolaire contenant la date donnée (typiquement "aujourd'hui").
     */
    public static function currentQuarter(\DateTimeImmutable $now): int
    {
        $monthsSinceSeptember = ((int) $now->format('n') - 9 + 12) % 12;

        return intdiv($monthsSinceSeptember, 3) + 1;
    }

    /**
     * Année de début de l'année scolaire contenant la date donnée : l'année
     * civile en cours à partir de septembre, l'année civile précédente de
     * janvier à août.
     */
    public static function currentSchoolYearStart(\DateTimeImmutable $now): int
    {
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        return $month >= 9 ? $year : $year - 1;
    }

    /**
     * @return array{0: int, 1: int} [année, trimestre]
     */
    public static function previousQuarter(int $year, int $quarter): array
    {
        return 1 === $quarter ? [$year - 1, 4] : [$year, $quarter - 1];
    }

    /**
     * @return array{0: int, 1: int} [année, trimestre]
     */
    public static function nextQuarter(int $year, int $quarter): array
    {
        return 4 === $quarter ? [$year + 1, 1] : [$year, $quarter + 1];
    }
}
