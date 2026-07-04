<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

/**
 * Algorithme absent de la whitelist (`printgate.jwt.allowed_algorithms`)
 * -- inclut notamment le rejet explicite de `none` et de tout algorithme
 * symétrique (HS256), qui n'ont pas de sens dans un modèle à clé publique
 * par poste.
 */
final class PrintGateJwtAlgorithmException extends PrintGateJwtException
{
    public function getStatusCode(): int
    {
        return 400;
    }
}
