<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

/**
 * Traduit un code de refus stable (PolicyDecision::REASON_*) en message
 * lisible pour un humain -- utilisé tel quel côté admin (flash) comme
 * référence pour l'agent PrintGate (même vocabulaire des deux côtés, cf.
 * décision du 2026-08-25). Volontairement une fonction pure sans état :
 * ni service injecté, ni dépendance -- juste du formatage.
 */
final class PrintRefusalMessageFormatter
{
    public static function format(PolicyDecision $decision): string
    {
        return match ($decision->reasonCode) {
            PolicyDecision::REASON_UNKNOWN_IDENTIFIER => 'Identifiant inconnu',
            PolicyDecision::REASON_NOT_ELIGIBLE_FOR_MUNICIPAL => 'Impression non éligible au financement mairie',
            PolicyDecision::REASON_RATE_NOT_CONFIGURED => 'Tarif non configuré',
            PolicyDecision::REASON_INSUFFICIENT_BALANCE => null !== $decision->missingCents
                ? sprintf('Solde insuffisant : %s € manquants', number_format($decision->missingCents / 100, 2, ',', ''))
                : 'Solde insuffisant',
            PolicyDecision::REASON_INVALID_REQUEST => 'Requête invalide',
            default => 'Requête refusée',
        };
    }
}
