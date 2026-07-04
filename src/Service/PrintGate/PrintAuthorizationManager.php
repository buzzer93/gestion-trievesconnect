<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintAuthorizationResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Étape 4 (module minimal) : implémentation TEMPORAIRE à décision fixe,
 * pilotée par le paramètre `printgate.default_authorization` plutôt qu'un
 * booléen en dur, pour pouvoir tester le cas de refus sans toucher au code.
 *
 * Volontairement SANS dépendance à PrintGateDeviceRepository ni
 * PrintGateJwtVerifier à ce stade : le couplage sera introduit à l'étape 5
 * (vérification JWT + résolution du poste) puis à l'étape 6 (règles
 * métier via PrintPolicyEvaluator). Ne pas anticiper cette dépendance ici.
 *
 * TODO étape 5 : injecter PrintGateJwtVerifier, résoudre le PrintGateDevice
 *                 à partir du JWT déjà vérifié en amont (listener dédié).
 * TODO étape 6 : déléguer la décision à PrintPolicyEvaluator plutôt que de
 *                 renvoyer la valeur fixe ci-dessous.
 */
final class PrintAuthorizationManager
{
    public function __construct(
        #[Autowire(param: 'printgate.default_authorization')]
        private readonly bool $defaultAuthorization,
    ) {
    }

    public function authorize(PrintAuthorizationRequest $request): PrintAuthorizationResponse
    {
        if ($this->defaultAuthorization) {
            return PrintAuthorizationResponse::authorized();
        }

        return PrintAuthorizationResponse::refused('Décision fixe de test (étape 4) : autorisation désactivée');
    }
}
