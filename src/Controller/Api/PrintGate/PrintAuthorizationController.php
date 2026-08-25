<?php

declare(strict_types=1);

namespace App\Controller\Api\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\Entity\PrintGateDevice;
use App\Service\PrintGate\PrintAuthorizationManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Contrôleur volontairement léger : récupère la requête déjà désérialisée
 * et validée par #[MapRequestPayload], délègue à PrintAuthorizationManager,
 * retourne la réponse JSON. Aucune logique métier ici (cf. règles de
 * l'architecture du projet : Controller -> Service -> Repository/Entity).
 *
 * TODO étape 5 : la vérification du JWT (signature, claims, bodyHash sur
 *                 les octets bruts) doit s'exécuter AVANT la désérialisation
 *                 automatique ci-dessous, via un listener sur
 *                 kernel.controller_arguments en priorité élevée -- ne pas
 *                 ajouter cette logique directement dans ce contrôleur.
 */
final class PrintAuthorizationController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route(
        '/api/printgate/authorize',
        name: 'api_printgate_authorize',
        methods: ['POST'],
        format: 'json',
    )]
    public function __invoke(
        Request $httpRequest,
        #[MapRequestPayload] PrintAuthorizationRequest $request,
        PrintAuthorizationManager $authorizationManager,
    ): JsonResponse {
        // Résolu par PrintGateAuthorizeIntegrityListener (kernel.request,
        // avant ce contrôleur) -- jamais null en pratique pour cette
        // route, le listener bloque déjà toute requête sans JWT valide.
        $device = $httpRequest->attributes->get('printGateDevice');
        \assert(null === $device || $device instanceof PrintGateDevice);

        $result = $authorizationManager->authorize($request, $device);

        return new JsonResponse(
            $this->serializer->serialize($result, 'json'),
            JsonResponse::HTTP_OK,
            [],
            true,
        );
    }
}
