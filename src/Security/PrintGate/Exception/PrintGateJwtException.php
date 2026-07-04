<?php

declare(strict_types=1);

namespace App\Security\PrintGate\Exception;

/**
 * Base commune des échecs de vérification JWT/intégrité PrintGate.
 * Chaque sous-classe porte le code HTTP à renvoyer (via getStatusCode())
 * et un message DESTINÉ AUX LOGS UNIQUEMENT -- PrintGateJwtExceptionListener
 * ne renvoie jamais ce message tel quel au client (cf. §10 du résumé
 * technique : ne jamais exposer le détail cryptographique d'un échec).
 */
abstract class PrintGateJwtException extends \RuntimeException
{
    abstract public function getStatusCode(): int;
}
