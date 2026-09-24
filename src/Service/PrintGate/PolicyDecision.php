<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

/**
 * Résultat de PrintPolicyEvaluator::evaluate() -- structure pure, aucune
 * logique. Distincte de PrintAuthorizationResponse (DTO de sortie HTTP) :
 * PolicyDecision est un objet-valeur interne au domaine métier, pas un
 * contrat d'API.
 *
 * $reasonCode est un code stable (cf. REASON_*), pas un message déjà
 * formaté pour l'utilisateur final -- la mise en forme (avec le montant
 * manquant le cas échéant) reste à la charge de l'appelant (API ou admin),
 * qui sait dans quelle langue/contexte l'afficher.
 */
final readonly class PolicyDecision
{
    public const REASON_UNKNOWN_IDENTIFIER = 'IDENTIFIANT_INCONNU';
    public const REASON_NOT_ELIGIBLE_FOR_MUNICIPAL = 'IMPRESSION_NON_ELIGIBLE_MAIRIE';
    public const REASON_RATE_NOT_CONFIGURED = 'TARIF_NON_CONFIGURE';
    public const REASON_INSUFFICIENT_BALANCE = 'SOLDE_INSUFFISANT';
    public const REASON_INVALID_REQUEST = 'REQUETE_INVALIDE';

    private function __construct(
        public bool $authorized,
        public ?string $reasonCode,
        public ?int $amountChargedCents = null,
        public ?string $fundingSource = null,
        public ?string $transactionReference = null,
        public ?int $missingCents = null,
    ) {
    }

    public static function authorized(int $amountChargedCents, string $fundingSource, string $transactionReference): self
    {
        return new self(true, null, $amountChargedCents, $fundingSource, $transactionReference);
    }

    public static function refused(string $reasonCode, ?int $missingCents = null): self
    {
        return new self(false, $reasonCode, missingCents: $missingCents);
    }
}
