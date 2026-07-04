<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

final class PrintGateJwtInvalidSignatureException extends PrintGateJwtException
{
    public function getStatusCode(): int
    {
        return 401;
    }
}
