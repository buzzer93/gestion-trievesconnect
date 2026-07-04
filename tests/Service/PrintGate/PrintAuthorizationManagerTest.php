<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintJobPayload;
use App\Service\PrintGate\PolicyDecision;
use App\Service\PrintGate\PrintAuthorizationManager;
use App\Service\PrintGate\PrintPolicyEvaluator;
use PHPUnit\Framework\TestCase;

final class PrintAuthorizationManagerTest extends TestCase
{
    public function testTranslatesAuthorizedDecisionToResponse(): void
    {
        $evaluator = $this->createMock(PrintPolicyEvaluator::class);
        $evaluator->method('evaluate')->willReturn(PolicyDecision::authorized());

        $manager = new PrintAuthorizationManager($evaluator);
        $response = $manager->authorize($this->request());

        self::assertTrue($response->authorizedImpression);
        self::assertNull($response->reason);
    }

    public function testTranslatesRefusedDecisionToResponseWithReason(): void
    {
        $evaluator = $this->createMock(PrintPolicyEvaluator::class);
        $evaluator->method('evaluate')->willReturn(PolicyDecision::refused('Une raison de test'));

        $manager = new PrintAuthorizationManager($evaluator);
        $response = $manager->authorize($this->request());

        self::assertFalse($response->authorizedImpression);
        self::assertSame('Une raison de test', $response->reason);
    }

    private function request(): PrintAuthorizationRequest
    {
        return new PrintAuthorizationRequest(
            identifier: 'j.dupont',
            computerId: 'POSTE-LINUX-01',
            hostname: 'poste-linux-01',
            printJob: new PrintJobPayload(
                jobId: 1,
                printerName: 'Imprimante-WiFi',
                documentName: 'document.pdf',
            ),
        );
    }
}
