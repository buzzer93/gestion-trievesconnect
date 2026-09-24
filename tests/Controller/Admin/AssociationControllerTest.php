<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Association;
use App\Entity\PrintTransaction;
use App\Entity\User;
use App\Repository\PrintTransactionLineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cf. PrintGateDeviceControllerTest pour la contrainte ROLE_ADMIN
 * (email exact 'contact@trievesconnect.fr').
 *
 * Depuis le 2026-08-25, printCharge() ne prend plus qu'un format/couleur/
 * copies -- le choix du solde (mairie ou personnel) n'est plus une option
 * de l'appelant, il est déterminé par PrintPolicyEvaluator. Ces tests
 * vérifient donc le comportement observable (quel solde bouge, quel
 * fundingSource est renvoyé), pas un paramètre "mode" qui n'existe plus.
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
        self::assertSelectorExists('[data-controller~="credit-modal"]');
    }

    /**
     * consumption() a été réécrite pour fusionner l'ancien journal
     * (PrintMunicipalConsumption) et le nouveau (PrintTransactionLine) --
     * aucun autre test n'exerce ce rendu, cf. le bug réel trouvé sur
     * print-pricing via ce même type de vérification manuelle.
     */
    public function testConsumptionPageRendersAfterMunicipalPrintCharge(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110010', personalCents: 1000, municipalCents: 1000);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'MONOCHROME', 'paperSize' => 'A4', 'copies' => 1]),
        );
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/admin/association/'.$association->getId().'/consumption');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('0,10', $crawler->filter('body')->text());
    }

    /**
     * Trimestre scolaire calé sur la rentrée (décision du 2026-08-26,
     * précisée le même jour : 4 trimestres de 3 mois comme avant, mais
     * décalés pour commencer en septembre). Sans year/quarter en query, la
     * page doit se placer sur le trimestre scolaire "aujourd'hui" (le test
     * tourne un 26/08, dans T4 juin-août de l'année scolaire commencée
     * l'année civile précédente) et proposer la bonne navigation
     * précédent/suivant, y compris le changement d'année scolaire autour
     * de T4 -> T1.
     */
    public function testConsumptionPageDefaultsToCurrentSchoolTermAndLinksNeighbours(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110015', personalCents: 0, municipalCents: 0);

        $crawler = $client->request('GET', '/admin/association/'.$association->getId().'/consumption');
        $now = new \DateTimeImmutable();
        $endOfSchoolYear = (int) $now->format('n') >= 9 ? (int) $now->format('Y') + 1 : (int) $now->format('Y');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Juin - Août '.$endOfSchoolYear, $crawler->filter('body')->text());
        self::assertStringContainsString(
            'quarter=3',
            $crawler->selectLink('Mars-Mai '.$endOfSchoolYear)->link()->getUri(),
        );
        self::assertStringContainsString(
            'quarter=1',
            $crawler->selectLink('Sept.-Nov. '.$endOfSchoolYear)->link()->getUri(),
        );
    }

    public function testShowPageRendersHistoryAfterPrintCharge(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110012', personalCents: 1000, municipalCents: 1000);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'MONOCHROME', 'paperSize' => 'A4', 'copies' => 1]),
        );
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/admin/association/'.$association->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('0,10', $crawler->filter('body')->text());
    }

    public function testShowRendersCreditModalAndBalanceCards(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110005', personalCents: 100, municipalCents: 200);

        $client->request('GET', '/admin/association/'.$association->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller~="credit-modal"]');
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
     * Cœur de la règle métier : impression éligible (A4 N&B), crédit
     * mairie insuffisant à lui seul -> bascule par unité sur le personnel
     * pour le reliquat, à SON tarif (cf. PrintPolicyEvaluatorTest pour le
     * détail du calcul).
     */
    public function testPrintChargeDebitsMunicipalBalanceBeforePersonal(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        // Tarifs seedés : MUNICIPAL MONOCHROME/A4 = 10c, ASSOCIATION MONOCHROME/A4 = 30c.
        $association = $this->buildAssociation('0611110002', personalCents: 100, municipalCents: 20);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'MONOCHROME', 'paperSize' => 'A4', 'copies' => 1]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        // 1 copie, coût mairie 10c couvert intégralement par les 20c disponibles.
        self::assertSame(10, $data['municipalCredits']);
        self::assertSame(100, $data['personalCredits']);
        self::assertSame(PrintTransaction::FUNDING_MUNICIPAL, $data['fundingSource']);

        $lineRepository = static::getContainer()->get(PrintTransactionLineRepository::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $refreshed = $entityManager->getRepository(Association::class)->find($association->getId());
        $lines = $lineRepository->findAllForCustomer($refreshed);
        self::assertCount(1, $lines);
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
        self::assertStringContainsString('Solde insuffisant', $data['error']);

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

    /**
     * Impression couleur : jamais éligible au financement mairie (seul
     * MONOCHROME/A4 est activé) -- 100% personnel même avec un solde
     * mairie confortable, au tarif ASSOCIATION (pas le tarif mairie).
     */
    public function testPrintChargeOnColorNeverTouchesMunicipalBalance(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110009', personalCents: 1000, municipalCents: 1000);

        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'COLOR', 'paperSize' => 'A4', 'copies' => 1]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(PrintTransaction::FUNDING_ASSOCIATION_PERSONAL, $data['fundingSource']);
        self::assertSame(1000, $data['municipalCredits']); // jamais touché
        self::assertSame(950, $data['personalCredits']); // tarif ASSOCIATION COLOR/A4 = 50c
    }

    /**
     * Cas réel du 2026-09-23 : impression débitée sur la mauvaise
     * association avec son crédit mairie. L'annulation doit la retirer de
     * la facturation mairie et recréditer le solde mairie (par défaut).
     */
    public function testCancelTransactionExcludesItFromMunicipalBillingAndRefunds(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110016', personalCents: 1000, municipalCents: 1000);
        $this->chargeOneMonochromeA4($client, $association);

        $crawler = $client->request('GET', '/admin/association/'.$association->getId());
        $form = $crawler->selectButton('Confirmer l\'annulation')->form(['reason' => 'Mauvaise association']);
        $client->submit($form);

        self::assertResponseRedirects('/admin/association/'.$association->getId());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $refreshed = $entityManager->getRepository(Association::class)->find($association->getId());
        self::assertSame(1000, $refreshed->getMunicipalBalanceCents());

        $transaction = static::getContainer()->get(PrintTransactionLineRepository::class)
            ->findAllForCustomer($refreshed)[0]->getTransaction();
        self::assertTrue($transaction->isCancelled());
        self::assertSame('Mauvaise association', $transaction->getCancellationReason());

        $now = new \DateTimeImmutable();
        $municipalLines = static::getContainer()->get(PrintTransactionLineRepository::class)->findMunicipalForQuarter(
            $refreshed,
            PrintTransactionLineRepository::currentSchoolYearStart($now),
            PrintTransactionLineRepository::currentQuarter($now),
        );
        self::assertCount(0, $municipalLines);

        // L'impression reste visible dans l'historique, marquée annulée.
        $crawler = $client->request('GET', '/admin/association/'.$association->getId());
        self::assertStringContainsString('Annulée', $crawler->filter('body')->text());
    }

    /**
     * Case "Rendre les crédits" décochée : l'admin a déjà corrigé le solde
     * à la main, l'annulation ne doit pas rembourser une seconde fois.
     */
    public function testCancelTransactionWithoutRefundKeepsBalances(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110017', personalCents: 1000, municipalCents: 1000);
        $this->chargeOneMonochromeA4($client, $association);

        $crawler = $client->request('GET', '/admin/association/'.$association->getId());
        $form = $crawler->selectButton('Confirmer l\'annulation')->form(['reason' => 'Déjà recrédité']);
        $form['refund']->untick();
        $client->submit($form);

        self::assertResponseRedirects('/admin/association/'.$association->getId());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $refreshed = $entityManager->getRepository(Association::class)->find($association->getId());
        self::assertSame(990, $refreshed->getMunicipalBalanceCents());
    }

    public function testCancelTransactionTwiceRefundsOnlyOnce(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110018', personalCents: 1000, municipalCents: 1000);
        $this->chargeOneMonochromeA4($client, $association);

        $crawler = $client->request('GET', '/admin/association/'.$association->getId());
        $form = $crawler->selectButton('Confirmer l\'annulation')->form(['reason' => 'Erreur']);
        $client->submit($form);
        // Double soumission du même formulaire (double clic, retour arrière...).
        $client->submit($form);

        self::assertResponseRedirects('/admin/association/'.$association->getId());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $refreshed = $entityManager->getRepository(Association::class)->find($association->getId());
        self::assertSame(1000, $refreshed->getMunicipalBalanceCents());
    }

    /**
     * La transaction est recherchée par référence ET par association de
     * l'URL : impossible d'annuler l'impression d'une autre association en
     * changeant l'id, même avec un jeton CSRF valide.
     */
    public function testCancelTransactionOfAnotherAssociationIsNotFound(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $owner = $this->buildAssociation('0611110019', personalCents: 1000, municipalCents: 1000);
        $other = $this->buildAssociation('0611110020', personalCents: 1000, municipalCents: 1000);
        $this->chargeOneMonochromeA4($client, $owner);

        $crawler = $client->request('GET', '/admin/association/'.$owner->getId());
        $form = $crawler->selectButton('Confirmer l\'annulation')->form();
        $reference = $this->lastTransactionReference($owner);

        $client->request('POST', '/admin/association/'.$other->getId().'/transactions/'.$reference.'/cancel', [
            '_token' => $form['_token']->getValue(),
            'reason' => 'Tentative',
            'refund' => '1',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCancelTransactionRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $association = $this->buildAssociation('0611110021', personalCents: 1000, municipalCents: 1000);
        $this->chargeOneMonochromeA4($client, $association);
        $reference = $this->lastTransactionReference($association);

        $client->request('POST', '/admin/association/'.$association->getId().'/transactions/'.$reference.'/cancel', [
            '_token' => 'invalid',
            'reason' => 'Erreur',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function chargeOneMonochromeA4(KernelBrowser $client, Association $association): void
    {
        $client->request(
            'POST',
            '/admin/association/'.$association->getId().'/print-charge',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['colorMode' => 'MONOCHROME', 'paperSize' => 'A4', 'copies' => 1]),
        );
        self::assertResponseIsSuccessful();
    }

    private function lastTransactionReference(Association $association): string
    {
        $lines = static::getContainer()->get(PrintTransactionLineRepository::class)->findAllForCustomer($association);

        return $lines[0]->getTransaction()->getReference();
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
            ->setReference($phoneNumber)
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
