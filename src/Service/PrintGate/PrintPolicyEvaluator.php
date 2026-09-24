<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\Entity\Association;
use App\Entity\PrintPriceRate;
use App\Entity\PrintTransaction;
use App\Entity\PrintTransactionLine;
use App\Repository\PrintTransactionRepository;

/**
 * Cœur métier de la décision d'impression : tarification, éligibilité au
 * financement mairie, répartition entre soldes, vérification de
 * suffisance et débit -- pour PrintGate (flux automatique) comme pour les
 * débits manuels depuis le back-office. Un seul point d'entrée, jamais
 * dupliqué (cf. contrainte projet).
 *
 * Règles (décidées le 2026-08-25, ne pas les redériver autrement) :
 * - le choix du solde n'est jamais une option laissée à l'appelant : il
 *   est entièrement déterminé ici, à partir des caractéristiques
 *   techniques de l'impression ;
 * - une impression est éligible au financement mairie si et seulement si
 *   une ligne PrintPriceRate scope=MUNICIPAL, activée, existe pour ce
 *   colorMode/paperSize (cf. PrintCostCalculator) -- aujourd'hui
 *   MONOCHROME/A4 uniquement, mais piloté par la donnée, pas par une
 *   condition figée ici ;
 * - impression éligible : le crédit mairie est débité en priorité, tant
 *   qu'il permet de couvrir des copies entières à son propre tarif ; le
 *   reliquat (copies non couvertes) bascule sur le crédit personnel de
 *   l'association, à SON tarif à elle (potentiellement différent du tarif
 *   mairie) -- bascule PAR UNITÉ, pas un simple partage proportionnel du
 *   montant total ;
 * - impression non éligible (couleur, autre format) : 100% sur le
 *   personnel, le crédit mairie n'est jamais sollicité, quel que soit son
 *   solde ;
 * - si le solde nécessaire (mairie + personnel complémentaire, ou
 *   personnel seul) ne suffit toujours pas : refus complet, aucun débit
 *   partiel.
 *
 * Idempotence : cf. PrintTransactionRepository::recordCharge() et la
 * contrainte UNIQUE (poste, jobId) portée par PrintTransaction.
 */
final class PrintPolicyEvaluator
{
    public function __construct(
        private readonly PrintCostCalculator $costCalculator,
        private readonly PrintTransactionRepository $transactionRepository,
    ) {
    }

    public function evaluate(PrintChargeContext $context): PolicyDecision
    {
        if ($context->copies < 1) {
            return PolicyDecision::refused(PolicyDecision::REASON_INVALID_REQUEST);
        }

        $existing = $this->findExistingTransaction($context);
        if (null !== $existing) {
            return PolicyDecision::authorized(
                $existing->getTotalAmountCents(),
                $existing->getFundingSourceSummary(),
                $existing->getReference(),
            );
        }

        $plan = $this->buildFundingPlan($context);
        if (null === $plan) {
            return PolicyDecision::refused(PolicyDecision::REASON_RATE_NOT_CONFIGURED);
        }

        $shortfall = $this->checkSufficiency($context->beneficiary, $plan);
        if (null !== $shortfall) {
            return PolicyDecision::refused(PolicyDecision::REASON_INSUFFICIENT_BALANCE, $shortfall);
        }

        $transaction = $this->charge($context, $plan);

        return PolicyDecision::authorized(
            $transaction->getTotalAmountCents(),
            $transaction->getFundingSourceSummary(),
            $transaction->getReference(),
        );
    }

    private function findExistingTransaction(PrintChargeContext $context): ?PrintTransaction
    {
        if (null === $context->device || null === $context->jobId) {
            return null;
        }

        return $this->transactionRepository->findByDeviceAndJobId($context->device, $context->jobId);
    }

    /**
     * @return list<array{fundingSource: string, copies: int, unitPriceCents: int}>|null
     */
    private function buildFundingPlan(PrintChargeContext $context): ?array
    {
        $beneficiary = $context->beneficiary;

        if (!$beneficiary instanceof Association) {
            $unitPrice = $this->costCalculator->unitPriceCents(PrintPriceRate::SCOPE_CLIENT, $context->colorMode, $context->paperSize);
            if (null === $unitPrice) {
                return null;
            }

            return [['fundingSource' => PrintTransaction::FUNDING_CUSTOMER, 'copies' => $context->copies, 'unitPriceCents' => $unitPrice]];
        }

        $associationUnitPrice = $this->costCalculator->unitPriceCents(PrintPriceRate::SCOPE_ASSOCIATION, $context->colorMode, $context->paperSize);
        if (null === $associationUnitPrice) {
            return null;
        }

        $municipalUnitPrice = $this->costCalculator->unitPriceCents(PrintPriceRate::SCOPE_MUNICIPAL, $context->colorMode, $context->paperSize);

        if (null === $municipalUnitPrice) {
            // Non éligible au financement mairie (pas de tarif mairie
            // activé pour cette combinaison) -- 100% personnel, la mairie
            // n'est jamais sollicitée.
            return [['fundingSource' => PrintTransaction::FUNDING_ASSOCIATION_PERSONAL, 'copies' => $context->copies, 'unitPriceCents' => $associationUnitPrice]];
        }

        $affordableByMunicipal = $municipalUnitPrice > 0
            ? intdiv($beneficiary->getMunicipalBalanceCents(), $municipalUnitPrice)
            : $context->copies;
        $municipalCopies = min($context->copies, $affordableByMunicipal);
        $personalCopies = $context->copies - $municipalCopies;

        $plan = [];
        if ($municipalCopies > 0) {
            $plan[] = ['fundingSource' => PrintTransaction::FUNDING_MUNICIPAL, 'copies' => $municipalCopies, 'unitPriceCents' => $municipalUnitPrice];
        }
        if ($personalCopies > 0) {
            $plan[] = ['fundingSource' => PrintTransaction::FUNDING_ASSOCIATION_PERSONAL, 'copies' => $personalCopies, 'unitPriceCents' => $associationUnitPrice];
        }

        return $plan;
    }

    /**
     * La part MUNICIPAL du plan est construite pour toujours tenir dans le
     * solde mairie disponible (cf. buildFundingPlan) -- seule la part
     * personnelle (CUSTOMER ou ASSOCIATION_PERSONAL) peut réellement
     * manquer. Retourne le montant manquant en centimes, ou null si tout
     * est couvert.
     */
    private function checkSufficiency(\App\Entity\Customer $beneficiary, array $plan): ?int
    {
        $personalNeeded = 0;
        foreach ($plan as $line) {
            if (PrintTransaction::FUNDING_MUNICIPAL !== $line['fundingSource']) {
                $personalNeeded += $line['copies'] * $line['unitPriceCents'];
            }
        }

        if (0 === $personalNeeded) {
            return null;
        }

        $available = $beneficiary->getBalanceCents();

        return $personalNeeded > $available ? $personalNeeded - $available : null;
    }

    private function charge(PrintChargeContext $context, array $plan): PrintTransaction
    {
        $beneficiary = $context->beneficiary;

        $transaction = new PrintTransaction(
            customer: $beneficiary,
            printGateDevice: $context->device,
            jobId: $context->jobId,
            colorMode: $context->colorMode,
            paperSize: $context->paperSize,
            pageCount: $context->pageCount,
            duplexMode: $context->duplexMode,
            createdBy: $context->createdBy,
            motif: $context->motif,
        );

        foreach ($plan as $line) {
            $amountCents = $line['copies'] * $line['unitPriceCents'];
            $transaction->addLine(new PrintTransactionLine($line['fundingSource'], $line['copies'], $line['unitPriceCents']));

            if (PrintTransaction::FUNDING_MUNICIPAL === $line['fundingSource']) {
                /** @var Association $beneficiary */
                $beneficiary->removeMunicipalBalanceCents($amountCents);
            } else {
                $beneficiary->removeBalanceCents($amountCents);
            }
        }

        return $this->transactionRepository->recordCharge($transaction);
    }
}
