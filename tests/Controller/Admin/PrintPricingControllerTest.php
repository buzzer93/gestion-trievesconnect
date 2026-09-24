<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cf. PrintGateDeviceControllerTest pour la contrainte ROLE_ADMIN
 * (email exact 'contact@trievesconnect.fr').
 *
 * Ajouté après un bug réel détecté en vérification manuelle (2026-08-25) :
 * les 3 grilles (CLIENT/ASSOCIATION/MUNICIPAL) sont rendues via des
 * `{% embed ... only %}` -- `only` coupe l'accès aux variables externes,
 * y compris dans les blocks surchargés ; il faut explicitement repasser
 * chaque grille dans le `with {}` de son embed. Rien dans les autres
 * tests ne couvrait le simple rendu GET de cette page.
 */
final class PrintPricingControllerTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'contact@trievesconnect.fr';

    public function testIndexRendersAllThreeRateGrids(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $crawler = $client->request('GET', '/admin/print-pricing/');

        self::assertResponseIsSuccessful();
        // 4 lignes par grille (2 formats x 2 couleurs) x 3 grilles = 12 champs de prix.
        self::assertCount(12, $crawler->filter('input[type="number"]'));
        // Seule la grille MUNICIPAL porte des cases "Activé".
        self::assertCount(4, $crawler->filter('input[type="checkbox"]'));
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
