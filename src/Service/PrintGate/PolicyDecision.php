<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

/**
 * Résultat de PrintPolicyEvaluator::evaluate() -- structure pure, aucune
 * logique. Distincte de PrintAuthorizationResponse (DTO de sortie HTTP) :
 * PolicyDecision est un objet-valeur interne au domaine métier, pas un
 * contrat d'API.
 */
final readonly class PolicyDecision
{
    private function __construct(
        public bool $authorized,
        public ?string $reason,
    ) {
    }

    public static function authorized(): self
    {
        return new self(true, null);
    }

    public static function refused(string $reason): self
    {
        return new self(false, $reason);
    }
}
