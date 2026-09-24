<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\Repository\PrintPriceRateRepository;

/**
 * Résout le tarif unitaire (prix par copie) applicable pour une
 * combinaison couleur/format, dans une grille donnée (CLIENT, ASSOCIATION
 * ou MUNICIPAL -- cf. PrintPriceRate::SCOPE_*). Source unique utilisée par
 * PrintPolicyEvaluator (flux PrintGate automatique et débits manuels
 * back-office confondus) -- pas de grille de prix codée en dur côté JS,
 * désynchronisée de celle-ci.
 *
 * Renvoie un prix unitaire (pas un total pré-multiplié par les copies) :
 * PrintPolicyEvaluator a besoin du prix à l'unité pour répartir une
 * impression entre plusieurs sources de financement à des tarifs
 * différents (bascule par unité, cf. sa PHPDoc).
 */
final class PrintCostCalculator
{
    public function __construct(
        private readonly PrintPriceRateRepository $priceRateRepository,
    ) {
    }

    /**
     * Retourne null si aucun tarif activé n'est configuré pour cette
     * combinaison dans cette grille -- fail-closed, pour ne jamais débiter
     * à un tarif arbitraire ou nul. Pour scope=MUNICIPAL, null signifie
     * aussi bien "pas de tarif" que "non éligible au financement mairie" :
     * les deux se traduisent par la même absence de ligne activée.
     */
    public function unitPriceCents(string $scope, string $colorMode, string $paperSize): ?int
    {
        $rate = $this->priceRateRepository->findOneEnabledByScopeAndFormat($scope, $colorMode, $paperSize);

        return $rate?->getPriceCents();
    }
}
