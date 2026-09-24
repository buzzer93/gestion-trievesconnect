<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

/**
 * `exp` dépassé, ou `iat` situé dans le futur au-delà de la tolérance de
 * clock skew configurée (`printgate.jwt.clock_skew_seconds`).
 */
final class PrintGateJwtExpiredException extends PrintGateJwtException
{
    public function getStatusCode(): int
    {
        return 401;
    }
}
