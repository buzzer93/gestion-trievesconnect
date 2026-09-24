<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\DTO\PrintGate\PrintJobPayload;
use App\Entity\Customer;
use App\Service\PrintGate\PolicyDecision;
use App\Service\PrintGate\PrintAuthorizationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Intégration plutôt qu'unitaire avec mocks (cf. PrintPolicyEvaluatorTest
 * pour la raison) : PrintAuthorizationManager est le point où
 * "identifiant brut -> bénéficiaire résolu" a lieu, ça vaut la peine de le
 * vérifier avec un vrai CustomerRepository plutôt qu'un mock qui
 * présupposerait le comportement testé.
 */
final class PrintAuthorizationManagerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PrintAuthorizationManager $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->manager = static::getContainer()->get(PrintAuthorizationManager::class);
    }

    public function testUnknownIdentifierIsRefused(): void
    {
        $response = $this->manager->authorize($this->request('0600000000'), null);

        self::assertFalse($response->authorized);
        self::assertSame(PolicyDecision::REASON_UNKNOWN_IDENTIFIER, $response->reason);
    }

    public function testKnownCustomerIsAuthorizedAndCharged(): void
    {
        $this->buildCustomer('0699100001', balanceCents: 100);

        $response = $this->manager->authorize($this->request('0699100001'), null);

        self::assertTrue($response->authorized);
        self::assertSame(50, $response->amountChargedCents);
        self::assertSame('CUSTOMER', $response->fundingSource);
        self::assertNotNull($response->transactionReference);
    }

    private function request(string $identifier): PrintAuthorizationRequest
    {
        return new PrintAuthorizationRequest(
            identifier: $identifier,
            computerId: 'POSTE-LINUX-01',
            hostname: 'poste-linux-01',
            printJob: new PrintJobPayload(
                jobId: 1,
                printerName: 'Imprimante-WiFi',
                documentName: 'document.pdf',
                copies: 1,
                paperSize: 'A4',
                colorMode: 'COLOR',
            ),
        );
    }

    private function buildCustomer(string $phoneNumber, int $balanceCents): Customer
    {
        $existing = $this->em->getRepository(Customer::class)->findOneBy(['phoneNumber' => $phoneNumber]);
        if (null !== $existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        $customer = new Customer();
        $customer->setName('Client Test PrintAuthorizationManager')->setPhoneNumber($phoneNumber)->setBalanceCents($balanceCents);
        $this->em->persist($customer);
        $this->em->flush();

        return $customer;
    }
}
