<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\Entity\Customer;
use App\Entity\PrintGateDevice;
use App\Entity\User;

/**
 * Entrée de PrintPolicyEvaluator::evaluate() -- délibérément distincte de
 * App\DTO\PrintGate\PrintAuthorizationRequest (contrat HTTP de l'API
 * PrintGate, avec computerId/hostname/identifiant brut) : ce contexte est
 * un objet du domaine métier, déjà résolu (bénéficiaire identifié, poste
 * PrintGate résolu si applicable). C'est ce qui permet au même
 * PrintPolicyEvaluator de servir aussi bien le flux PrintGate automatique
 * (résolution faite par PrintAuthorizationManager) que les débits manuels
 * depuis l'admin (résolution déjà faite par le contrôleur, qui a
 * l'entité via la route) -- sans dupliquer la logique de tarification, de
 * répartition et de débit à deux endroits.
 */
final readonly class PrintChargeContext
{
    public function __construct(
        public Customer $beneficiary,
        public string $colorMode,
        public string $paperSize,
        public int $copies,
        public int $pageCount = 0,
        public ?string $duplexMode = null,
        public ?PrintGateDevice $device = null,
        public ?int $jobId = null,
        public ?User $createdBy = null,
        public ?string $motif = null,
    ) {
    }
}
