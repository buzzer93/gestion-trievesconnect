<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\Repository\PrintPriceRateRepository;

/**
 * Calcule le coût d'une impression à partir de la grille tarifaire
 * configurée en admin (page "Tarifs d'impression"). Source unique utilisée
 * aussi bien par le flux PrintGate automatique (cf. PrintPolicyEvaluator)
 * que par les débits manuels depuis le back-office (CustomerController,
 * AssociationController) -- évite d'avoir une deuxième grille de prix
 * codée en dur côté JS, désynchronisée de celle-ci.
 */
final class PrintCostCalculator
{
    public function __construct(
        private readonly PrintPriceRateRepository $priceRateRepository,
    ) {
    }

    /**
     * Retourne null si aucun tarif n'est configuré pour cette combinaison
     * -- fail-closed, pour ne jamais débiter à un tarif arbitraire ou nul.
     */
    public function computeCostCents(string $colorMode, string $paperSize, int $copies): ?int
    {
        $rate = $this->priceRateRepository->findOneByTypeAndFormat($colorMode, $paperSize);

        if (null === $rate) {
            return null;
        }

        return $rate->getPriceCents() * max(1, $copies);
    }
}
