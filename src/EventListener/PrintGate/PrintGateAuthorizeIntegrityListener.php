<?php

declare(strict_types=1);

namespace App\EventListener\PrintGate;

use App\Repository\PrintGateUsedTokenRepository;
use App\Security\PrintGate\Exception\PrintGateJwtClaimException;
use App\Security\PrintGate\Exception\PrintGateJwtMalformedException;
use App\Security\PrintGate\PrintGateJwtVerifier;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Vérifie le JWT et l'intégrité du corps AVANT toute désérialisation.
 *
 * IMPORTANT (correction par rapport au sous-plan initial) : cette
 * vérification doit avoir lieu sur `kernel.request`, PAS sur
 * `kernel.controller_arguments`. La résolution des arguments du contrôleur
 * (et donc la désérialisation par #[MapRequestPayload]) a lieu AVANT que
 * `kernel.controller_arguments` ne soit distribué -- un listener sur cet
 * événement arriverait donc trop tard pour lire le corps brut avant
 * désérialisation. `kernel.request` est le bon point d'ancrage.
 *
 * Priorité 10 : le routeur (RouterListener, priorité 32) doit avoir déjà
 * résolu `_route` pour qu'on puisse cibler précisément l'endpoint
 * PrintGate sans dupliquer la configuration de routing ici.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final class PrintGateAuthorizeIntegrityListener
{
    private const TARGET_ROUTE = 'api_printgate_authorize';

    public function __construct(
        private readonly PrintGateJwtVerifier $verifier,
        private readonly PrintGateUsedTokenRepository $usedTokenRepository,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (self::TARGET_ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        $jwt = $this->extractBearerToken($request->headers->get('Authorization'));
        $rawBody = $request->getContent(); // brut, AVANT toute désérialisation

        [$device, $claims] = $this->verifier->verify($jwt, $rawBody);

        // IMPORTANT : markAsSeen() modifie une entité déjà managée (donc
        // suivie par l'UnitOfWork de Doctrine) mais ne la persiste pas
        // elle-même -- elle doit être positionnée AVANT l'appel qui
        // déclenche réellement un flush() (markAsUsed ci-dessous), sans
        // quoi le changement de lastSeenAt est silencieusement perdu.
        // (Bug détecté après coup : dans la première version de ce
        // listener, markAsUsed() était appelé avant markAsSeen(), et
        // lastSeenAt n'était donc jamais écrit en base.)
        $device->markAsSeen(new \DateTimeImmutable());

        // L'anti-rejeu n'est appliqué qu'après un verify() réussi, pour ne
        // consommer le jti qu'une fois toutes les autres vérifications
        // passées (signature, claims, intégrité du corps). Le flush()
        // interne à markAsUsed() persiste dans la foulée le changement de
        // lastSeenAt ci-dessus, puisque $device est déjà managé.
        $this->usedTokenRepository->markAsUsed(
            (string) $claims['jti'],
            $device,
            (new \DateTimeImmutable())->setTimestamp((int) $claims['exp']),
        );

        // Mis à disposition du contrôleur/service pour les étapes suivantes
        // (règles métier, étape 6) -- ne pas dupliquer la résolution du
        // poste plus loin dans la pile.
        $request->attributes->set('printGateDevice', $device);
    }

    private function extractBearerToken(?string $authorizationHeader): string
    {
        if (null === $authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new PrintGateJwtClaimException('En-tête Authorization absent ou mal formé');
        }

        $token = substr($authorizationHeader, \strlen('Bearer '));

        if ('' === $token) {
            throw new PrintGateJwtMalformedException('Token JWT vide');
        }

        return $token;
    }
}
