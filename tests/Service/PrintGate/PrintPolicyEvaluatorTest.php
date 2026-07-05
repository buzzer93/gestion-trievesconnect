<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintJobPayload;
use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\PrintGate\PrintPolicyEvaluator;
use PHPUnit\Framework\TestCase;

final class PrintPolicyEvaluatorTest extends TestCase
{
    public function testUnknownIdentifierIsRefused(): void
    {
        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPrintGateIdentifier')->willReturn(null);
        $customerRepository->expects(self::never())->method('debitBalance');

        $evaluator = new PrintPolicyEvaluator($customerRepository);
        $decision = $evaluator->evaluate($this->request());

        self::assertFalse($decision->authorized);
        self::assertSame('Identifiant inconnu', $decision->reason);
    }

    public function testInsufficientCreditsAreRefusedWithoutDebiting(): void
    {
        $customer = $this->customerWithBalance(29); // < 30 (MONOCHROME/A4/1 copie)

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPrintGateIdentifier')->willReturn($customer);
        $customerRepository->expects(self::never())->method('debitBalance');

        $evaluator = new PrintPolicyEvaluator($customerRepository);
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertFalse($decision->authorized);
        self::assertSame('Crédits insuffisants', $decision->reason);
    }

    public function testSufficientCreditsAreAuthorizedAndDebited(): void
    {
        $customer = $this->customerWithBalance(30);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPrintGateIdentifier')->willReturn($customer);
        $customerRepository->expects(self::once())->method('debitBalance')->with($customer, 30);

        $evaluator = new PrintPolicyEvaluator($customerRepository);
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertTrue($decision->authorized);
        self::assertNull($decision->reason);
    }

    /**
     * Grille tarifaire identique à la modale de débit manuel
     * (templates/admin/customer/index.html.twig) : MONOCHROME/A4 30c,
     * MONOCHROME/A3 60c, COLOR/A4 50c, COLOR/A3 100c, multiplié par le
     * nombre de copies. pageCount n'intervient volontairement pas (comme
     * le système manuel existant).
     *
     * @return list<array{0: string, 1: ?string, 2: int, 3: int}>
     */
    public static function pricingCases(): array
    {
        return [
            'monochrome A4' => ['MONOCHROME', 'A4', 1, 30],
            'monochrome A3' => ['MONOCHROME', 'A3', 1, 60],
            'color A4' => ['COLOR', 'A4', 1, 50],
            'color A3' => ['COLOR', 'A3', 1, 100],
            'color A4 x3 copies' => ['COLOR', 'A4', 3, 150],
            'colorMode absent -> tarif monochrome' => [null, 'A4', 1, 30],
            'paperSize absent -> tarif A4' => ['COLOR', null, 1, 50],
        ];
    }

    /**
     * @dataProvider pricingCases
     */
    public function testPricingGrid(?string $colorMode, ?string $paperSize, int $copies, int $expectedCents): void
    {
        $customer = $this->customerWithBalance($expectedCents);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPrintGateIdentifier')->willReturn($customer);
        $customerRepository->expects(self::once())->method('debitBalance')->with($customer, $expectedCents);

        $evaluator = new PrintPolicyEvaluator($customerRepository);
        $decision = $evaluator->evaluate($this->request(colorMode: $colorMode, paperSize: $paperSize, copies: $copies));

        self::assertTrue($decision->authorized);
    }

    /**
     * pageCount ne doit jamais influencer le tarif (cf. PHPDoc de
     * PrintPolicyEvaluator::PRICES_CENTS) : un document de 100000 pages
     * coûte le même prix qu'un document d'une page, à copies égales.
     */
    public function testPageCountDoesNotAffectPrice(): void
    {
        $customer = $this->customerWithBalance(30);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPrintGateIdentifier')->willReturn($customer);
        $customerRepository->expects(self::once())->method('debitBalance')->with($customer, 30);

        $evaluator = new PrintPolicyEvaluator($customerRepository);
        $decision = $evaluator->evaluate($this->request(pageCount: 100000, colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertTrue($decision->authorized);
    }

    private function customerWithBalance(int $cents): Customer
    {
        $customer = new Customer();
        $customer->setBalanceCents($cents);

        return $customer;
    }

    private function request(
        int $pageCount = 4,
        ?string $colorMode = 'COLOR',
        ?string $paperSize = 'A4',
        int $copies = 1,
    ): PrintAuthorizationRequest {
        return new PrintAuthorizationRequest(
            identifier: 'j.dupont',
            computerId: 'POSTE-LINUX-01',
            hostname: 'poste-linux-01',
            printJob: new PrintJobPayload(
                jobId: 42,
                printerName: 'Imprimante-WiFi',
                documentName: 'document.pdf',
                pageCount: $pageCount,
                copies: $copies,
                paperSize: $paperSize,
                colorMode: $colorMode,
                duplexMode: 'ONE_SIDED',
            ),
        );
    }
}
