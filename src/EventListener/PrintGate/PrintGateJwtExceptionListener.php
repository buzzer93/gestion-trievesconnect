<?php

declare(strict_types=1);

namespace App\EventListener\PrintGate;

use App\Security\PrintGate\Exception\PrintGateJwtException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Traduit les échecs de vérification JWT/intégrité en réponse JSON
 * générique. Le détail exact de l'échec (message des exceptions
 * PrintGateJwtException) est loggé mais JAMAIS renvoyé au client --
 * cf. résumé technique §10 : ne pas exposer d'information cryptographique
 * exploitable par un attaquant pour affiner ses tentatives.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class PrintGateJwtExceptionListener
{
    private const GENERIC_MESSAGES = [
        400 => 'Requête invalide',
        401 => 'Non autorisé',
        403 => 'Accès refusé',
        409 => 'Conflit',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof PrintGateJwtException) {
            return;
        }

        $statusCode = $exception->getStatusCode();

        $this->logger->warning('PrintGate: requête d\'autorisation rejetée', [
            'exception' => $exception::class,
            'detail' => $exception->getMessage(),
            'status' => $statusCode,
        ]);

        $event->setResponse(new JsonResponse([
            'authorizedImpression' => false,
            'reason' => self::GENERIC_MESSAGES[$statusCode] ?? 'Requête rejetée',
        ], $statusCode));
    }
}
