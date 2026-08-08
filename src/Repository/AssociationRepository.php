<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\PrintGate\PrintJobPayload;
use App\Entity\Association;
use App\Entity\PrintMunicipalConsumption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Association>
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

    /**
     * Débite le coût d'une impression pour une association : priorité au
     * crédit mairie, puis bascule sur le crédit personnel pour la part
     * restante si le crédit mairie ne couvre pas tout (cf. décision prise
     * avec l'utilisateur : le crédit mairie fonctionne comme un
     * "bouclier" absorbé en premier). Journalise chaque part réellement
     * prélevée dans PrintMunicipalConsumption (une ligne par source
     * sollicitée) : la part mairie sert de justificatif pour la
     * facturation trimestrielle, les deux ensemble alimentent l'historique
     * complet affiché sur la fiche association.
     *
     * Persistance ici plutôt que dans PrintPolicyEvaluator, comme
     * CustomerRepository::debitBalance() (même raison : ce service n'a pas
     * d'autre besoin de l'EntityManager).
     */
    public function debitForPrintJob(Association $association, int $totalCostCents, PrintJobPayload $printJob): void
    {
        $municipalPortion = min($totalCostCents, $association->getMunicipalBalanceCents());
        $personalPortion = $totalCostCents - $municipalPortion;

        if ($municipalPortion > 0) {
            $association->removeMunicipalBalanceCents($municipalPortion);
        }
        if ($personalPortion > 0) {
            $association->removeBalanceCents($personalPortion);
        }

        $em = $this->getEntityManager();

        if ($municipalPortion > 0) {
            $em->persist(new PrintMunicipalConsumption(
                association: $association,
                printJobId: $printJob->jobId,
                pageCount: $printJob->pageCount,
                copies: $printJob->copies,
                colorMode: $printJob->colorMode,
                paperSize: $printJob->paperSize,
                amountSpentCents: $municipalPortion,
                fundingSource: PrintMunicipalConsumption::SOURCE_MUNICIPAL,
            ));
        }

        if ($personalPortion > 0) {
            $em->persist(new PrintMunicipalConsumption(
                association: $association,
                printJobId: $printJob->jobId,
                pageCount: $printJob->pageCount,
                copies: $printJob->copies,
                colorMode: $printJob->colorMode,
                paperSize: $printJob->paperSize,
                amountSpentCents: $personalPortion,
                fundingSource: PrintMunicipalConsumption::SOURCE_PERSONAL,
            ));
        }

        $em->flush();
    }
}
