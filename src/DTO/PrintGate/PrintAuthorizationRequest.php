<?php

declare(strict_types=1);

namespace App\DTO\PrintGate;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps JSON envoyé par l'agent à POST /api/printgate/authorize.
 *
 * Volontairement dépourvu de toute référence au JWT : le token de
 * signature est transporté séparément (header Authorization) et vérifié
 * en amont par PrintGateJwtVerifier (étape 5), pas par ce DTO. Ne pas
 * coupler cette classe à la logique de sécurité.
 */
final readonly class PrintAuthorizationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Identifiant invalide')]
        #[Assert\Length(max: 190)]
        public string $identifier,

        #[Assert\NotBlank]
        #[Assert\Length(max: 190)]
        public string $computerId,

        #[Assert\Length(max: 255)]
        public ?string $hostname,

        #[Assert\Valid]
        public PrintJobPayload $printJob,
    ) {
    }
}
