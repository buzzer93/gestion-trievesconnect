<?php

declare(strict_types=1);

namespace App\DTO\PrintGate;

/**
 * Réponse JSON renvoyée par POST /api/printgate/authorize.
 * Structure pure : ne contient aucune logique.
 */
final readonly class PrintAuthorizationResponse
{
    public function __construct(
        public bool $authorizedImpression,
        public ?string $reason = null,
    ) {
    }

    public static function authorized(): self
    {
        return new self(true);
    }

    public static function refused(string $reason): self
    {
        return new self(false, $reason);
    }
}
