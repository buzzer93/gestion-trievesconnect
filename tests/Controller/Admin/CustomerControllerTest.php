<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cf. PrintGateDeviceControllerTest pour la contrainte ROLE_ADMIN
 * (email exact 'contact@trievesconnect.fr').
 */
final class CustomerControllerTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'contact@trievesconnect.fr';

    public function testIndexRendersCreditModalWithoutError(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $client->request('GET', '/admin/customer/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="credit-modal"]');
    }

    /**
     * printCharge() a changé de contrat ({colorMode, paperSize, copies} au
     * lieu de {cents} brut) pour utiliser les tarifs configurés
     * (PrintCostCalculator) plutôt qu'une grille dupliquée côté JS -- même
     * source que le débit associatif.
     */
    public function testPrintChargeUsesConfiguredRate(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $customer = $this->buildCustomer('0622220001', balanceCents: 100);

        $client->request(
            'POST',
            '/admin/customer/'.$customer->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'COLOR', 'paperSize' => 'A4', 'copies' => 1]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        // Tarif seedé COLOR/A4 = 50 centimes (cf. Version20260808130000).
        self::assertSame(50, $data['credits']);
    }

    public function testPrintChargeRefusedWhenBalanceInsufficient(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $customer = $this->buildCustomer('0622220002', balanceCents: 10);

        $client->request(
            'POST',
            '/admin/customer/'.$customer->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'COLOR', 'paperSize' => 'A4', 'copies' => 1]),
        );

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Solde insuffisant', $data['error']);
    }

    private function buildCustomer(string $phoneNumber, int $balanceCents): Customer
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $entityManager->getRepository(Customer::class)->findOneBy(['phoneNumber' => $phoneNumber]);
        if (null !== $existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        $customer = new Customer();
        $customer->setName('Client Test')
            ->setPhoneNumber($phoneNumber)
            ->setBalanceCents($balanceCents);

        $entityManager->persist($customer);
        $entityManager->flush();

        return $customer;
    }

    private function buildUser(string $email): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $entityManager->getRepository(User::class)->findOneBy(['username' => $email]);
        if (null !== $existing) {
            return $existing;
        }

        $user = (new User())
            ->setUsername($email)
            ->setEmail($email)
            ->setPassword('not-used-by-loginUser');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
