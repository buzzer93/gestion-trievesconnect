<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

/**
 * `jti` déjà utilisé -- rejeu détecté (cf. PrintGateUsedToken).
 */
final class PrintGateReplayException extends PrintGateJwtException
{
    public function getStatusCode(): int
    {
        return 409;
    }
}
