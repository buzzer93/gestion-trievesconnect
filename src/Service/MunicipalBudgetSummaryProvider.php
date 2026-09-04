<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AssociationRepository;
use App\Repository\PrintMunicipalConsumptionRepository;
use App\Repository\PrintTransactionLineRepository;

/**
 * Calcule la consommation du crédit mairie par association sur un
 * trimestre donné. Extrait de MunicipalBudgetController pour être
 * réutilisé à la fois par la page de récap et par l'export PDF.
 */
class MunicipalBudgetSummaryProvider
{
    public function __construct(
        private readonly AssociationRepository $associationRepository,
        private readonly PrintMunicipalConsumptionRepository $consumptionRepository,
        private readonly PrintTransactionLineRepository $lineRepository,
    ) {
    }

    /**
     * @return array{byAssociation: array<int, array{association: \App\Entity\Association, amountCents: int}>, totalCents: int}
     */
    public function forQuarter(int $year, int $quarter): array
    {
        // Une entrée par association, y compris celles sans consommation
        // ce trimestre (0 €), pour un récap complet plutôt qu'une liste
        // tronquée aux seules associations actives.
        $byAssociation = [];
        foreach ($this->associationRepository->findAll() as $association) {
            $byAssociation[$association->getId()] = [
                'association' => $association,
                'amountCents' => 0,
            ];
        }

        $totalCents = 0;

        // Fusionne l'ancien journal (trimestres déjà facturés avant le
        // 2026-08-25) et le nouveau (seul alimenté désormais) -- cf.
        // PHPDoc de Version20260825170000.
        foreach ($this->consumptionRepository->findMunicipalForQuarterAllAssociations($year, $quarter) as $entry) {
            $id = $entry->getAssociation()->getId();
            $byAssociation[$id]['amountCents'] += $entry->getAmountSpentCents();
            $totalCents += $entry->getAmountSpentCents();
        }

        foreach ($this->lineRepository->findMunicipalForQuarterAllAssociations($year, $quarter) as $line) {
            $id = $line->getTransaction()->getCustomer()->getId();
            if (isset($byAssociation[$id])) {
                $byAssociation[$id]['amountCents'] += $line->getAmountCents();
            }
            $totalCents += $line->getAmountCents();
        }

        return ['byAssociation' => $byAssociation, 'totalCents' => $totalCents];
    }
}
