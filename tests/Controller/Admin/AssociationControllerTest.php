<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Association;
use App\Entity\PrintMunicipalConsumption;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cf. PrintGateDeviceControllerTest pour la contrainte ROLE_ADMIN
 * (email exact 'contact@trievesconnect.fr').
 */
final class AssociationControllerTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'contact@trievesconnect.fr';

    public function testIndexRendersCreditModalWithoutError(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $client->request('GET', '/admin/association/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="credit-modal"]');
    }

    public function testShowRendersCreditModalAndBalanceCards(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110005', personalCents: 100, municipalCents: 200);

        $client->request('GET', '/admin/association/'.$association->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="credit-modal"]');
        self::assertSelectorExists('[data-credit-modal-target="pageBalancePersonal"]');
        self::assertSelectorExists('[data-credit-modal-target="pageBalanceMunicipal"]');
    }

    public function testMunicipalCreditsAddsToMunicipalBalanceOnly(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110006', personalCents: 100, municipalCents: 200);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/municipal-credits',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['mode' => 'add', 'cents' => 50]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(250, $data['municipalCredits']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $refreshed = $entityManager->getRepository(Association::class)->find($association->getId());
        self::assertSame(250, $refreshed->getMunicipalBalanceCents());
        // Le solde personnel ne doit jamais bouger via cette route.
        self::assertSame(100, $refreshed->getBalanceCents());
    }

    public function testCreditsAddsToPersonalBalanceOnly(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110001', personalCents: 100, municipalCents: 200);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/credits',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['mode' => 'add', 'cents' => 50]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(150, $data['credits']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $refreshed = $entityManager->getRepository(Association::class)->find($association->getId());
        self::assertSame(150, $refreshed->getBalanceCents());
        // Le crédit mairie ne doit jamais bouger via cette route.
        self::assertSame(200, $refreshed->getMunicipalBalanceCents());
    }

    /**
     * Cœur de la règle métier : le crédit mairie doit être débité en
     * priorité, la part personnelle ne couvre que le reliquat -- cf.
     * AssociationRepository::debitForPrintJob(). Vérifie aussi que
     * l'historique PrintMunicipalConsumption est bien alimenté par un
     * débit manuel (comme par le flux PrintGate automatique).
     */
    public function testPrintChargeDebitsMunicipalBalanceBeforePersonal(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        // Tarif seedé : MONOCHROME/A4 = 30 centimes (cf. Version20260808130000).
        $association = $this->buildAssociation('0611110002', personalCents: 100, municipalCents: 20);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'MONOCHROME', 'paperSize' => 'A4', 'copies' => 1]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        // Coût 30c : 20c pris sur le solde mairie (épuisé), 10c sur le personnel.
        self::assertSame(0, $data['municipalCredits']);
        self::assertSame(90, $data['personalCredits']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entries = $entityManager->getRepository(PrintMunicipalConsumption::class)->findBy(['association' => $association]);
        self::assertCount(2, $entries); // une ligne mairie + une ligne personnelle
    }

    public function testPrintChargeRefusedWhenBothBalancesInsufficient(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110003', personalCents: 5, municipalCents: 5);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'MONOCHROME', 'paperSize' => 'A4', 'copies' => 1]),
        );

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Solde insuffisant', $data['error']);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $refreshed = $entityManager->getRepository(Association::class)->find($association->getId());
        // Rien débité malgré le refus.
        self::assertSame(5, $refreshed->getBalanceCents());
        self::assertSame(5, $refreshed->getMunicipalBalanceCents());
    }

    public function testPrintChargeRefusedForUnconfiguredRate(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110004', personalCents: 10000, municipalCents: 0);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'SEPIA', 'paperSize' => 'A5', 'copies' => 1]),
        );

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Tarif non configuré', $data['error']);
    }

    private function buildAssociation(string $phoneNumber, int $personalCents, int $municipalCents): Association
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $entityManager->getRepository(Association::class)->findOneBy(['phoneNumber' => $phoneNumber]);
        if (null !== $existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }

        $association = new Association();
        $association->setName('Association Test')
            ->setPhoneNumber($phoneNumber)
            ->setBalanceCents($personalCents)
            ->setMunicipalBalanceCents($municipalCents);

        $entityManager->persist($association);
        $entityManager->flush();

        return $association;
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
