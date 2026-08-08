<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintJobPayload;
use App\Entity\Association;
use App\Entity\Customer;
use App\Entity\PrintPriceRate;
use App\Repository\AssociationRepository;
use App\Repository\CustomerRepository;
use App\Repository\PrintPriceRateRepository;
use App\Service\PrintGate\PrintPolicyEvaluator;
use PHPUnit\Framework\TestCase;

final class PrintPolicyEvaluatorTest extends TestCase
{
    public function testUnknownIdentifierIsRefused(): void
    {
        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn(null);
        $customerRepository->expects(self::never())->method('debitBalance');

        $associationRepository = $this->createMock(AssociationRepository::class);
        $associationRepository->expects(self::never())->method('debitForPrintJob');

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request());

        self::assertFalse($decision->authorized);
        self::assertSame('Identifiant inconnu', $decision->reason);
    }

    public function testInsufficientCreditsAreRefusedWithoutDebiting(): void
    {
        $customer = $this->customerWithBalance(29); // < 30 (MONOCHROME/A4/1 copie)

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn($customer);
        $customerRepository->expects(self::never())->method('debitBalance');

        $associationRepository = $this->createMock(AssociationRepository::class);

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertFalse($decision->authorized);
        self::assertSame('Crédits insuffisants', $decision->reason);
    }

    public function testSufficientCreditsAreAuthorizedAndDebited(): void
    {
        $customer = $this->customerWithBalance(30);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn($customer);
        $customerRepository->expects(self::once())->method('debitBalance')->with($customer, 30);

        $associationRepository = $this->createMock(AssociationRepository::class);

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertTrue($decision->authorized);
        self::assertNull($decision->reason);
    }

    /**
     * Grille tarifaire de test, identique à celle seedée par la migration
     * (cf. PrintPriceRate) : MONOCHROME/A4 30c, MONOCHROME/A3 60c,
     * COLOR/A4 50c, COLOR/A3 100c, multiplié par le nombre de copies.
     * pageCount n'intervient volontairement pas (comme le système manuel
     * existant).
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
        $customerRepository->method('findOneByPhoneNumber')->willReturn($customer);
        $customerRepository->expects(self::once())->method('debitBalance')->with($customer, $expectedCents);

        $associationRepository = $this->createMock(AssociationRepository::class);

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request(colorMode: $colorMode, paperSize: $paperSize, copies: $copies));

        self::assertTrue($decision->authorized);
    }

    /**
     * pageCount ne doit jamais influencer le tarif (cf. PHPDoc de
     * PrintPolicyEvaluator::computeCostCents) : un document de 100000
     * pages coûte le même prix qu'un document d'une page, à copies
     * égales.
     */
    public function testPageCountDoesNotAffectPrice(): void
    {
        $customer = $this->customerWithBalance(30);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn($customer);
        $customerRepository->expects(self::once())->method('debitBalance')->with($customer, 30);

        $associationRepository = $this->createMock(AssociationRepository::class);

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request(pageCount: 100000, colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertTrue($decision->authorized);
    }

    /**
     * Aucun PrintPriceRate configuré pour la combinaison demandée -> refus
     * fail-closed ("Tarif non configuré"), jamais un tarif à 0 ou par
     * défaut. Aucun débit ne doit avoir lieu.
     */
    public function testUnconfiguredRateIsRefusedWithoutDebiting(): void
    {
        $customer = $this->customerWithBalance(1000);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn($customer);
        $customerRepository->expects(self::never())->method('debitBalance');

        $associationRepository = $this->createMock(AssociationRepository::class);
        $associationRepository->expects(self::never())->method('debitForPrintJob');

        $priceRateRepository = $this->createMock(PrintPriceRateRepository::class);
        $priceRateRepository->method('findOneByTypeAndFormat')->willReturn(null);

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $priceRateRepository);
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertFalse($decision->authorized);
        self::assertSame('Tarif non configuré', $decision->reason);
    }

    /**
     * Une association avec assez de crédit mairie pour couvrir tout le
     * job ne doit jamais toucher à son solde personnel -- c'est
     * CustomerRepository::debitBalance() (le chemin "client normal") qui
     * ne doit jamais être appelé ici, seul AssociationRepository::debitForPrintJob()
     * doit l'être.
     */
    public function testAssociationDebitsMunicipalCreditFirst(): void
    {
        $association = $this->associationWithBalances(personalCents: 0, municipalCents: 5000);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn($association);
        $customerRepository->expects(self::never())->method('debitBalance');

        $associationRepository = $this->createMock(AssociationRepository::class);
        $associationRepository->expects(self::once())
            ->method('debitForPrintJob')
            ->with($association, 30, self::isInstanceOf(PrintJobPayload::class));

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertTrue($decision->authorized);
    }

    /**
     * Crédit mairie insuffisant pour couvrir tout le job, mais le solde
     * personnel complète : doit être autorisé (le partage se fait dans
     * AssociationRepository::debitForPrintJob(), pas testé ici en détail
     * -- cf. son test dédié si besoin d'aller plus loin).
     */
    public function testAssociationFallsBackToPersonalCreditWhenMunicipalCreditInsufficient(): void
    {
        $association = $this->associationWithBalances(personalCents: 100, municipalCents: 10);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn($association);

        $associationRepository = $this->createMock(AssociationRepository::class);
        $associationRepository->expects(self::once())->method('debitForPrintJob')->with($association, 30, self::isInstanceOf(PrintJobPayload::class));

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertTrue($decision->authorized);
    }

    /**
     * Ni le crédit mairie ni le crédit personnel, combinés, ne couvrent
     * le job -> refus, aucun débit.
     */
    public function testAssociationRefusedWhenCombinedCreditsInsufficient(): void
    {
        $association = $this->associationWithBalances(personalCents: 10, municipalCents: 5);

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneByPhoneNumber')->willReturn($association);

        $associationRepository = $this->createMock(AssociationRepository::class);
        $associationRepository->expects(self::never())->method('debitForPrintJob');

        $evaluator = new PrintPolicyEvaluator($customerRepository, $associationRepository, $this->priceRateRepository());
        $decision = $evaluator->evaluate($this->request(colorMode: 'MONOCHROME', paperSize: 'A4'));

        self::assertFalse($decision->authorized);
        self::assertSame('Crédits insuffisants', $decision->reason);
    }

    private function customerWithBalance(int $cents): Customer
    {
        $customer = new Customer();
        $customer->setBalanceCents($cents);

        return $customer;
    }

    private function associationWithBalances(int $personalCents, int $municipalCents): Association
    {
        $association = new Association();
        $association->setBalanceCents($personalCents);
        $association->setMunicipalBalanceCents($municipalCents);

        return $association;
    }

    /**
     * Grille tarifaire de test (identique aux valeurs seedées par la
     * migration), retournée par un mock de PrintPriceRateRepository plutôt
     * qu'une constante en dur dans PrintPolicyEvaluator (cf. tarifs
     * désormais configurables depuis l'admin).
     */
    private function priceRateRepository(): PrintPriceRateRepository
    {
        $grid = [
            'MONOCHROME' => ['A4' => 30, 'A3' => 60],
            'COLOR' => ['A4' => 50, 'A3' => 100],
        ];

        $repository = $this->createMock(PrintPriceRateRepository::class);
        $repository->method('findOneByTypeAndFormat')
            ->willReturnCallback(static function (string $colorMode, string $paperSize) use ($grid): ?PrintPriceRate {
                if (!isset($grid[$colorMode][$paperSize])) {
                    return null;
                }

                return new PrintPriceRate($colorMode, $paperSize, $grid[$colorMode][$paperSize]);
            })
        ;

        return $repository;
    }

    private function request(
        int $pageCount = 4,
        ?string $colorMode = 'COLOR',
        ?string $paperSize = 'A4',
        int $copies = 1,
    ): PrintAuthorizationRequest {
        return new PrintAuthorizationRequest(
            identifier: '0600000000',
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
