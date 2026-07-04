<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintAuthorizationResponse;

/**
 * Étape 6 : délègue désormais la décision à PrintPolicyEvaluator, au lieu
 * de la décision fixe de l'étape 4 (paramètre printgate.default_authorization
 * -- devenu obsolet, à retirer de config/services.yaml).
 *
 * Ne reçoit volontairement PAS le PrintGateDevice résolu par le listener
 * JWT (disponible dans $request->attributes->get('printGateDevice') côté
 * HttpFoundation) : aucune règle V1 n'en a besoin. Si une règle V2 en a
 * besoin un jour (ex. quota par poste), ajouter le paramètre à ce moment
 * -- pas avant (cf. règles projet : éviter les abstractions/paramètres
 * anticipés sans besoin réel).
 */
final class PrintAuthorizationManager
{
    public function __construct(
        private readonly PrintPolicyEvaluator $policyEvaluator,
    ) {
    }

    public function authorize(PrintAuthorizationRequest $request): PrintAuthorizationResponse
    {
        $decision = $this->policyEvaluator->evaluate($request);

        if (!$decision->authorized) {
            return PrintAuthorizationResponse::refused($decision->reason ?? 'Requête refusée');
        }

        return PrintAuthorizationResponse::authorized();
    }
}
