<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Association;
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
     * Customer et Association partagent la même table (Single Table
     * Inheritance) -- une requête sans filtre sur la liste "Clients"
     * remonterait aussi les associations (cf. correction du 2026-08-25).
     */
    public function testIndexExcludesAssociations(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $entityManager->getRepository(Association::class)->findOneBy(['phoneNumber' => '0622229999']);
        if (!$existing) {
            $existing = new Association();
            $existing->setName('Association ne doit pas apparaître ici')->setPhoneNumber('0622229999');
            $entityManager->persist($existing);
            $entityManager->flush();
        }

        $crawler = $client->request('GET', '/admin/customer/');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'Association ne doit pas apparaître ici',
            $crawler->filter('#customerTable')->text(),
        );
    }

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
        self::assertStringContainsString('Solde insuffisant', $data['error']);
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
