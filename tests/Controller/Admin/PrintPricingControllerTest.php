<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\PrintPriceRate;
use App\Entity\User;
use App\Repository\PrintPriceRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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
 *
 * La base de test n'est pas réinitialisée entre deux tests : les tests
 * d'ajout utilisent un format dédié (TEST_FORMAT) et le suppriment en
 * tearDown, et les décomptes attendus sont lus en base plutôt que figés.
 */
final class PrintPricingControllerTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'contact@trievesconnect.fr';
    private const TEST_FORMAT = 'TEST5';

    protected function tearDown(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($entityManager->getRepository(PrintPriceRate::class)->findBy(['paperSize' => self::TEST_FORMAT]) as $rate) {
            $entityManager->remove($rate);
        }
        $entityManager->flush();

        parent::tearDown();
    }

    public function testIndexRendersAllThreeRateGrids(): void
    {
        $client = $this->createAdminClient();
        $rateCount = $this->countRates();
        $municipalCount = count($this->rateRepository()->findAllByScope(PrintPriceRate::SCOPE_MUNICIPAL));

        $crawler = $client->request('GET', '/admin/print-pricing/');

        self::assertResponseIsSuccessful();
        // Un champ de prix par ligne, toutes grilles confondues.
        self::assertCount($rateCount, $crawler->filter('table input[type="number"]'));
        // Seule la grille MUNICIPAL porte des cases "Activé".
        self::assertCount($municipalCount, $crawler->filter('input[type="checkbox"]'));
        // Un bouton "Supprimer" par ligne, chacun relié à son formulaire de suppression.
        self::assertCount($rateCount, $crawler->filter('button[form^="delete-rate-"]'));
        self::assertCount($rateCount, $crawler->filter('form[id^="delete-rate-"]'));
        // Services demandés par le client le 2026-11-25 (cf. Version20261125110000).
        self::assertSelectorTextContains('body', 'Plastification');
        self::assertSelectorTextContains('body', 'Reliure de dossier');
    }

    public function testAdminCanAddRateThenDeleteIt(): void
    {
        $client = $this->createAdminClient();
        $rateCount = $this->countRates();

        $crawler = $client->request('GET', '/admin/print-pricing/');
        $client->submit($crawler->filter('#add-rate-form')->form([
            'scope' => PrintPriceRate::SCOPE_ASSOCIATION,
            'color_mode' => 'COLOR',
            'paper_size' => strtolower(self::TEST_FORMAT),
            'price' => '0,35',
        ]));

        self::assertResponseRedirects('/admin/print-pricing/');
        $rate = $this->findTestRate(PrintPriceRate::SCOPE_ASSOCIATION);
        self::assertNotNull($rate, 'Le format saisi en minuscules doit être enregistré en majuscules.');
        self::assertSame(35, $rate->getPriceCents());
        self::assertTrue($rate->isEnabled());

        $crawler = $client->followRedirect();
        self::assertCount($rateCount + 1, $crawler->filter('table input[type="number"]'));

        $client->submit($crawler->filter('#delete-rate-'.$rate->getId())->form());

        self::assertResponseRedirects('/admin/print-pricing/');
        self::assertNull($this->findTestRate(PrintPriceRate::SCOPE_ASSOCIATION));
    }

    public function testAddingAnExistingCombinationIsRefused(): void
    {
        $client = $this->createAdminClient();
        $rateCount = $this->countRates();

        $crawler = $client->request('GET', '/admin/print-pricing/');
        $client->submit($crawler->filter('#add-rate-form')->form([
            'scope' => PrintPriceRate::SCOPE_CLIENT,
            'color_mode' => 'MONOCHROME',
            'paper_size' => 'A4',
            'price' => '1',
        ]));

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ce tarif existe déjà');
        self::assertCount($rateCount, $crawler->filter('table input[type="number"]'));
    }

    public function testAddingRateWithInvalidPriceIsRefused(): void
    {
        $client = $this->createAdminClient();

        $crawler = $client->request('GET', '/admin/print-pricing/');
        $form = $crawler->filter('#add-rate-form')->form();
        $client->request('POST', '/admin/print-pricing/new', [
            '_token' => $form->get('_token')->getValue(),
            'scope' => PrintPriceRate::SCOPE_CLIENT,
            'color_mode' => 'COLOR',
            'paper_size' => self::TEST_FORMAT,
            'price' => '-1',
        ]);

        self::assertResponseRedirects('/admin/print-pricing/');
        self::assertNull($this->findTestRate(PrintPriceRate::SCOPE_CLIENT));
    }

    public function testDeleteWithoutValidCsrfTokenIsDenied(): void
    {
        $client = $this->createAdminClient();
        $rate = $this->rateRepository()->findAll()[0];

        $client->request('DELETE', '/admin/print-pricing/'.$rate->getId().'/delete', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->rateRepository()->find($rate->getId()));
    }

    private function rateRepository(): PrintPriceRateRepository
    {
        return static::getContainer()->get(PrintPriceRateRepository::class);
    }

    private function countRates(): int
    {
        return count($this->rateRepository()->findAll());
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        return $client;
    }

    private function findTestRate(string $scope): ?PrintPriceRate
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(PrintPriceRate::class)->findOneBy([
            'scope' => $scope,
            'paperSize' => self::TEST_FORMAT,
        ]);
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
