<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Association;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cf. PrintGateDeviceControllerTest pour la contrainte ROLE_ADMIN
 * (email exact 'contact@trievesconnect.fr').
 *
 * Aucun test n'existait pour cette page avant le 2026-08-25, alors que son
 * contrôleur fusionne désormais l'ancien journal (PrintMunicipalConsumption)
 * et le nouveau (PrintTransactionLine) -- cf. le bug réel trouvé sur
 * print-pricing via une simple vérification manuelle du rendu.
 */
final class MunicipalBudgetControllerTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'contact@trievesconnect.fr';

    public function testIndexRendersAfterMunicipalPrintCharge(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110011', personalCents: 1000, municipalCents: 1000);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'MONOCHROME', 'paperSize' => 'A4', 'copies' => 1]),
        );
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/admin/municipal-budget/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Association Test', $crawler->filter('body')->text());
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
