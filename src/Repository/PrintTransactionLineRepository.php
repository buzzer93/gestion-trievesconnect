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
     * Trimestre SCOLAIRE (pas calendaire), décision du 2026-08-26 : T1 =
     * septembre-décembre, T2 = janvier-avril, T3 = mai-août. Mois fixes
     * chaque année (pas les dates officielles Éducation Nationale, qui
     * bougent) -- chaque trimestre reste contenu dans une seule année
     * civile, aucun ne chevauche un 31/12.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} Bornes [début, fin[ du trimestre
     */
    public static function quarterBounds(int $year, int $quarter): array
    {
        $startMonth = match ($quarter) {
            1 => 9,
            2 => 1,
            3 => 5,
            default => 1,
        };
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));

        return [$start, $start->modify('+4 months')];
    }

    /**
     * Trimestre scolaire contenant la date donnée (typiquement "aujourd'hui"),
     * pour la valeur par défaut affichée sur les pages consommation/budget.
     */
    public static function currentQuarter(\DateTimeImmutable $now): int
    {
        $month = (int) $now->format('n');

        return match (true) {
            $month >= 9 => 1,
            $month >= 5 => 3,
            default => 2,
        };
    }

    /**
     * Trimestre scolaire précédent. Le T1 (sept-déc) est chronologiquement
     * LE DERNIER de l'année civile mais porté par le numéro "1" -- donc
     * seul le passage T2 -> T1 change d'année (recule d'un an), les deux
     * autres restent sur l'année en cours.
     *
     * @return array{0: int, 1: int} [année, trimestre]
     */
    public static function previousQuarter(int $year, int $quarter): array
    {
        return match ($quarter) {
            1 => [$year, 3],
            2 => [$year - 1, 1],
            3 => [$year, 2],
            default => [$year, 1],
        };
    }

    /**
     * Trimestre scolaire suivant -- symétrique de previousQuarter() :
     * seul le passage T1 -> T2 avance d'un an.
     *
     * @return array{0: int, 1: int} [année, trimestre]
     */
    public static function nextQuarter(int $year, int $quarter): array
    {
        return match ($quarter) {
            1 => [$year + 1, 2],
            2 => [$year, 3],
            3 => [$year, 1],
            default => [$year, 1],
        };
    }
}
