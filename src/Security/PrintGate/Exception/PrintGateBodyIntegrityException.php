<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

/**
 * Le `bodyHash` du JWT ne correspond pas au SHA-256 des octets bruts du
 * corps HTTP reçu -- cf. résumé technique §3.4. C'est le test le plus
 * critique de l'étape 5 : toute divergence, même d'un seul octet, doit
 * aboutir ici.
 */
final class PrintGateBodyIntegrityException extends PrintGateJwtException
{
    public function getStatusCode(): int
    {
        return 400;
    }
}
