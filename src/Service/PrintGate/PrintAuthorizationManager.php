<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintAuthorizationResponse;
use App\Entity\PrintGateDevice;
use App\Repository\CustomerRepository;

/**
 * Traduit la requête HTTP PrintGate (identifiant brut, poste déjà résolu
 * par PrintGateAuthorizeIntegrityListener) en PrintChargeContext, délègue
 * la décision à PrintPolicyEvaluator, traduit le résultat en réponse HTTP.
 *
 * La résolution "identifiant -> bénéficiaire" reste ici (pas dans
 * PrintPolicyEvaluator) : c'est une préoccupation propre au flux PrintGate
 * (l'identifiant est un numéro de téléphone brut envoyé par l'agent) --
 * un débit manuel admin part déjà d'une Association/Customer résolue via
 * la route, sans identifiant à parser.
 */
final class PrintAuthorizationManager
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly PrintPolicyEvaluator $policyEvaluator,
    ) {
    }

    public function authorize(PrintAuthorizationRequest $request, ?PrintGateDevice $device): PrintAuthorizationResponse
    {
        $beneficiary = $this->customerRepository->findOneByPhoneNumber($request->identifier);

        if (null === $beneficiary) {
            return PrintAuthorizationResponse::refused(PolicyDecision::REASON_UNKNOWN_IDENTIFIER);
        }

        $printJob = $request->printJob;

        $context = new PrintChargeContext(
            beneficiary: $beneficiary,
            colorMode: strtoupper((string) ($printJob->colorMode ?? '')),
            paperSize: strtoupper((string) ($printJob->paperSize ?? '')),
            copies: $printJob->copies,
            pageCount: $printJob->pageCount,
            duplexMode: $printJob->duplexMode,
            device: $device,
            jobId: $printJob->jobId,
        );

        $decision = $this->policyEvaluator->evaluate($context);

        return PrintAuthorizationResponse::fromDecision($decision);
    }
}
