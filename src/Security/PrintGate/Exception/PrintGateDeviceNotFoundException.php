<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

final class PrintGateDeviceNotFoundException extends PrintGateJwtException
{
    public function getStatusCode(): int
    {
        return 401;
    }
}
