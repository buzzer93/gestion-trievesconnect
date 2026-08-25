<?php

declare(strict_types=1);

namespace App\DTO\PrintGate;

/**
 * Réponse JSON renvoyée par POST /api/printgate/authorize. Structure
 * pure : ne contient aucune logique.
 *
 * Ne renvoie jamais les soldes complets de l'association/du client :
 * PrintGate n'en a pas besoin pour libérer ou refuser le job CUPS, juste
 * de savoir si l'impression est autorisée et à quel tarif (cf. décision du
 * 2026-08-25). `fundingSource` vaut "MIXED" quand le crédit mairie a
 * partiellement couvert l'impression et que le personnel a complété.
 *
 * Remplace l'ancien contrat minimal (`authorizedImpression` seul, sans
 * tarif ni référence) -- l'agent PrintGate n'étant pas encore déployé, pas
 * de champ de compatibilité conservé (cf. décision utilisateur).
 */
final readonly class PrintAuthorizationResponse
{
    public function __construct(
        public bool $authorized,
        public ?string $reason = null,
        public ?int $amountChargedCents = null,
        public ?string $fundingSource = null,
        public ?string $transactionReference = null,
    ) {
    }

    public static function fromDecision(\App\Service\PrintGate\PolicyDecision $decision): self
    {
        if ($decision->authorized) {
            return new self(
                authorized: true,
                amountChargedCents: $decision->amountChargedCents,
                fundingSource: $decision->fundingSource,
                transactionReference: $decision->transactionReference,
            );
        }

        return new self(authorized: false, reason: $decision->reasonCode);
    }

    public static function refused(string $reason): self
    {
        return new self(authorized: false, reason: $reason);
    }
}
