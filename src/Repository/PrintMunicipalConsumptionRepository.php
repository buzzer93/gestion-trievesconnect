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
     * trimestre donné, pour la page admin de facturation -- ne remonte que
     * la source MUNICIPAL, seule concernée par la facture mairie (cf.
     * AssociationController::consumption()). Le regroupement par type
     * (couleur/format) et le total se calculent côté PHP à l'affichage :
     * peu de lignes par trimestre, pas besoin d'agrégation SQL (cf. règles
     * projet anti-surengineering).
     *
     * @return PrintMunicipalConsumption[]
     */
    public function findForQuarter(Association $association, int $year, int $quarter): array
    {
        [$start, $end] = self::quarterBounds($year, $quarter);

        return $this->createQueryBuilder('c')
            ->andWhere('c.association = :association')
            ->andWhere('c.fundingSource = :fundingSource')
            ->andWhere('c.createdAt >= :start')
            ->andWhere('c.createdAt < :end')
            ->setParameter('association', $association)
            ->setParameter('fundingSource', PrintMunicipalConsumption::SOURCE_MUNICIPAL)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Impressions financées par le crédit mairie de TOUTES les associations
     * sur un trimestre donné, pour le récap de la page "Budget mairie".
     * Le regroupement par association se fait côté PHP dans le contrôleur,
     * même logique que findForQuarter().
     *
     * @return PrintMunicipalConsumption[]
     */
    public function findMunicipalForQuarterAllAssociations(int $year, int $quarter): array
    {
        [$start, $end] = self::quarterBounds($year, $quarter);

        return $this->createQueryBuilder('c')
            ->andWhere('c.fundingSource = :fundingSource')
            ->andWhere('c.createdAt >= :start')
            ->andWhere('c.createdAt < :end')
            ->setParameter('fundingSource', PrintMunicipalConsumption::SOURCE_MUNICIPAL)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Historique complet d'une association, mairie et perso confondus --
     * la fiche association (page "show") les sépare à l'affichage via
     * PrintMunicipalConsumption::isMunicipal().
     *
     * @return PrintMunicipalConsumption[]
     */
    public function findAllForAssociation(Association $association): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.association = :association')
            ->setParameter('association', $association)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Déléguée à PrintTransactionLineRepository -- les deux dépôts sont
     * fusionnés dans les contrôleurs pour les mêmes (year, quarter), les
     * bornes doivent donc rester identiques. Cf. sa docblock pour la
     * définition du trimestre scolaire (décision du 2026-08-26).
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} Bornes [début, fin[ du trimestre
     */
    public static function quarterBounds(int $year, int $quarter): array
    {
        return PrintTransactionLineRepository::quarterBounds($year, $quarter);
    }
}
