<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintGate;

use App\Entity\Association;
use App\Entity\Customer;
use App\Entity\PrintTransaction;
use App\Service\PrintGate\PolicyDecision;
use App\Service\PrintGate\PrintChargeContext;
use App\Service\PrintGate\PrintPolicyEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration (base réelle, cf. .env.test) plutôt qu'unitaires à
 * base de mocks : PrintPolicyEvaluator est `final` (donc impossible à
 * doubler) et persiste réellement une PrintTransaction dans une
 * transaction DB explicite -- le doubler viderait le test de tout son
 * intérêt (l'essentiel à vérifier est justement l'effet en base).
 *
 * Tarifs utilisés dans ces tests (seedés par Version20260825160000, cf.
 * son PHPDoc) : CLIENT/ASSOCIATION identiques (MONOCHROME/A4=30c,
 * MONOCHROME/A3=60c, COLOR/A4=50c, COLOR/A3=100c), MUNICIPAL
 * MONOCHROME/A4=10c activé, tout le reste désactivé.
 */
final class PrintPolicyEvaluatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PrintPolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->evaluator = static::getContainer()->get(PrintPolicyEvaluator::class);
    }

    public function testCustomerChargedAtClientRate(): void
    {
        $customer = $this->buildCustomer('0699000001', balanceCents: 100);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $customer,
            colorMode: 'COLOR',
            paperSize: 'A4',
            copies: 1,
        ));

        self::assertTrue($decision->authorized);
        self::assertSame(50, $decision->amountChargedCents);
        self::assertSame(PrintTransaction::FUNDING_CUSTOMER, $decision->fundingSource);
        self::assertSame(50, $customer->getBalanceCents());
    }

    public function testCustomerRefusedWhenInsufficientBalance(): void
    {
        $customer = $this->buildCustomer('0699000002', balanceCents: 10);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $customer,
            colorMode: 'COLOR',
            paperSize: 'A4',
            copies: 1,
        ));

        self::assertFalse($decision->authorized);
        self::assertSame(PolicyDecision::REASON_INSUFFICIENT_BALANCE, $decision->reasonCode);
        self::assertSame(40, $decision->missingCents);
        // Rien débité malgré le refus.
        self::assertSame(10, $customer->getBalanceCents());
    }

    /**
     * A4 N&B avec un solde mairie qui couvre tout : jamais touché au
     * personnel.
     */
    public function testAssociationEligiblePrintChargesMunicipalFirst(): void
    {
        $association = $this->buildAssociation('0699000003', personalCents: 1000, municipalCents: 1000);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $association,
            colorMode: 'MONOCHROME',
            paperSize: 'A4',
            copies: 5,
        ));

        self::assertTrue($decision->authorized);
        self::assertSame(50, $decision->amountChargedCents); // 5 x 10c
        self::assertSame(PrintTransaction::FUNDING_MUNICIPAL, $decision->fundingSource);
        self::assertSame(950, $association->getMunicipalBalanceCents());
        self::assertSame(1000, $association->getBalanceCents());
    }

    /**
     * Scénario de l'exemple validé avec l'utilisateur (2026-08-25) :
     * 5 copies A4 N&B, tarif mairie 10c, tarif asso 30c, solde mairie
     * 30c -> 3 copies à tarif mairie (30c) + 2 copies à tarif asso (60c)
     * = 90c au total. Bascule PAR UNITÉ, pas un partage proportionnel du
     * montant.
     */
    public function testAssociationEligiblePrintSplitsPerUnitAtDifferentRates(): void
    {
        $association = $this->buildAssociation('0699000004', personalCents: 1000, municipalCents: 30);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $association,
            colorMode: 'MONOCHROME',
            paperSize: 'A4',
            copies: 5,
        ));

        self::assertTrue($decision->authorized);
        self::assertSame(90, $decision->amountChargedCents);
        self::assertSame('MIXED', $decision->fundingSource);
        self::assertSame(0, $association->getMunicipalBalanceCents());
        self::assertSame(940, $association->getBalanceCents()); // 1000 - 60

        // Vérifie le détail des lignes, pas seulement le total.
        $transaction = $this->findTransactionByReference($decision->transactionReference);
        $lines = $transaction->getLines();
        self::assertCount(2, $lines);
    }

    public function testAssociationEligiblePrintRefusedWhenBothBalancesInsufficient(): void
    {
        $association = $this->buildAssociation('0699000005', personalCents: 5, municipalCents: 5);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $association,
            colorMode: 'MONOCHROME',
            paperSize: 'A4',
            copies: 5,
        ));

        self::assertFalse($decision->authorized);
        self::assertSame(PolicyDecision::REASON_INSUFFICIENT_BALANCE, $decision->reasonCode);
        // Rien débité malgré le refus (pas de débit partiel de la part mairie).
        self::assertSame(5, $association->getMunicipalBalanceCents());
        self::assertSame(5, $association->getBalanceCents());
    }

    /**
     * Couleur : jamais éligible au financement mairie (seul MONOCHROME/A4
     * est activé) -- 100% personnel, même avec un solde mairie confortable.
     */
    public function testAssociationColorPrintNeverTouchesMunicipalBalance(): void
    {
        $association = $this->buildAssociation('0699000006', personalCents: 1000, municipalCents: 1000);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $association,
            colorMode: 'COLOR',
            paperSize: 'A4',
            copies: 1,
        ));

        self::assertTrue($decision->authorized);
        self::assertSame(50, $decision->amountChargedCents); // tarif ASSOCIATION, pas MUNICIPAL
        self::assertSame(PrintTransaction::FUNDING_ASSOCIATION_PERSONAL, $decision->fundingSource);
        self::assertSame(1000, $association->getMunicipalBalanceCents()); // jamais touché
        self::assertSame(950, $association->getBalanceCents());
    }

    /**
     * A3 N&B : format non activé pour la mairie (seul A4 l'est) -- même
     * comportement que la couleur.
     */
    public function testAssociationA3MonochromeNeverTouchesMunicipalBalance(): void
    {
        $association = $this->buildAssociation('0699000007', personalCents: 1000, municipalCents: 1000);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $association,
            colorMode: 'MONOCHROME',
            paperSize: 'A3',
            copies: 1,
        ));

        self::assertTrue($decision->authorized);
        self::assertSame(PrintTransaction::FUNDING_ASSOCIATION_PERSONAL, $decision->fundingSource);
        self::assertSame(1000, $association->getMunicipalBalanceCents());
    }

    public function testAssociationIneligiblePrintRefusedWhenPersonalInsufficientEvenWithMunicipalBalance(): void
    {
        $association = $this->buildAssociation('0699000008', personalCents: 10, municipalCents: 10000);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $association,
            colorMode: 'COLOR',
            paperSize: 'A4',
            copies: 1,
        ));

        self::assertFalse($decision->authorized);
        self::assertSame(PolicyDecision::REASON_INSUFFICIENT_BALANCE, $decision->reasonCode);
        self::assertSame(10000, $association->getMunicipalBalanceCents()); // jamais sollicité
    }

    public function testRefusedWhenRateNotConfigured(): void
    {
        $customer = $this->buildCustomer('0699000009', balanceCents: 100000);

        $decision = $this->evaluator->evaluate(new PrintChargeContext(
            beneficiary: $customer,
            colorMode: 'SEPIA',
            paperSize: 'A5',
            copies: 1,
        ));

        self::assertFalse($decision->authorized);
        self::assertSame(PolicyDecision::REASON_RATE_NOT_CONFIGURED, $decision->reasonCode);
    }

    private function buildCustomer(string $phoneNumber, int $balanceCents): Customer
    {
        $existing = $this->em->getRepository(Customer::class)->findOneBy(['phoneNumber' => $phoneNumber]);
        if (null !== $existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        $customer = new Customer();
        $customer->setName('Client Test PrintPolicyEvaluator')->setPhoneNumber($phoneNumber)->setBalanceCents($balanceCents);
        $this->em->persist($customer);
        $this->em->flush();

        return $customer;
    }

    private function buildAssociation(string $phoneNumber, int $personalCents, int $municipalCents): Association
    {
        $existing = $this->em->getRepository(Association::class)->findOneBy(['phoneNumber' => $phoneNumber]);
        if (null !== $existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        $association = new Association();
        $association->setName('Association Test PrintPolicyEvaluator')
            ->setPhoneNumber($phoneNumber)
            ->setBalanceCents($personalCents)
            ->setMunicipalBalanceCents($municipalCents);
        $this->em->persist($association);
        $this->em->flush();

        return $association;
    }

    private function findTransactionByReference(string $reference): PrintTransaction
    {
        $transaction = $this->em->getRepository(PrintTransaction::class)->findOneBy(['reference' => $reference]);
        self::assertNotNull($transaction);

        return $transaction;
    }
}
