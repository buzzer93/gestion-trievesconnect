<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintJobPayload;
use App\Service\PrintGate\PrintPolicyEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * PrintPolicyEvaluator est volontairement sans dépendance Doctrine/HTTP :
 * ces tests s'exécutent sans base de données ni kernel Symfony.
 */
final class PrintPolicyEvaluatorTest extends TestCase
{
    public function testNominalRequestIsAuthorized(): void
    {
        $evaluator = new PrintPolicyEvaluator();

        $decision = $evaluator->evaluate($this->request());

        self::assertTrue($decision->authorized);
        self::assertNull($decision->reason);
    }

    /**
     * V1 n'a aucune règle métier active (cf. PHPDoc de PrintPolicyEvaluator) :
     * ce test documente cet état, pas une limite de l'évaluateur lui-même.
     * Il devra être complété dès qu'une première règle V2 sera ajoutée.
     */
    public function testEvaluatorHasNoActiveRuleInV1(): void
    {
        $evaluator = new PrintPolicyEvaluator();

        $decisionWithManyPages = $evaluator->evaluate($this->request(pageCount: 100000));

        self::assertTrue(
            $decisionWithManyPages->authorized,
            'Aucune limite de pages n\'est active en V1 (cf. résumé technique §15/§16)',
        );
    }

    private function request(int $pageCount = 4): PrintAuthorizationRequest
    {
        return new PrintAuthorizationRequest(
            identifier: 'j.dupont',
            computerId: 'POSTE-LINUX-01',
            hostname: 'poste-linux-01',
            printJob: new PrintJobPayload(
                jobId: 42,
                printerName: 'Imprimante-WiFi',
                documentName: 'document.pdf',
                pageCount: $pageCount,
                copies: 1,
                paperSize: 'A4',
                colorMode: 'COLOR',
                duplexMode: 'ONE_SIDED',
            ),
        );
    }
}
