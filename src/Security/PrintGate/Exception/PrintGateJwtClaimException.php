<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

/**
 * `iss`/`aud` incorrects, ou `jti` absent/vide.
 */
final class PrintGateJwtClaimException extends PrintGateJwtException
{
    public function getStatusCode(): int
    {
        return 401;
    }
}
