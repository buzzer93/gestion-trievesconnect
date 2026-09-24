<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Association;
use App\Entity\PrintTransaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Annule une impression débitée par erreur (ex : mauvaise association
 * sélectionnée) -- la retire de la facturation mairie sans supprimer la
 * ligne (cf. PrintTransaction::$cancelledAt).
 *
 * Remboursement par défaut (décision du 2026-09-24) : chaque ligne est
 * recréditée sur la source qui l'a financée, symétrique du débit de
 * PrintPolicyEvaluator. Désactivable quand l'admin a déjà corrigé le
 * solde à la main, pour éviter un double remboursement.
 */
class PrintTransactionCanceller
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return bool false si la transaction était déjà annulée (aucun effet)
     */
    public function cancel(PrintTransaction $transaction, ?User $cancelledBy, string $reason, bool $refund): bool
    {
        if ($transaction->isCancelled()) {
            return false;
        }

        $transaction->cancel($cancelledBy, $reason);

        if ($refund) {
            $this->refund($transaction);
        }

        $this->em->flush();

        return true;
    }

    private function refund(PrintTransaction $transaction): void
    {
        $beneficiary = $transaction->getCustomer();

        foreach ($transaction->getLines() as $line) {
            if (PrintTransaction::FUNDING_MUNICIPAL === $line->getFundingSource() && $beneficiary instanceof Association) {
                $beneficiary->addMunicipalBalanceCents($line->getAmountCents());
            } else {
                $beneficiary->addBalanceCents($line->getAmountCents());
            }
        }
    }
}
