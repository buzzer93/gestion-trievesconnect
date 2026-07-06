<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\PrintGateDevice;
use App\Entity\PrintGateUsedToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * ATTENTION : User::getRoles() de ce projet accorde ROLE_ADMIN uniquement
 * si l'email vaut exactement 'contact@trievesconnect.fr' (règle en dur,
 * cf. src/Entity/User.php) -- setRoles() est ignoré pour ROLE_ADMIN.
 * buildUser() doit donc jouer sur l'email, pas sur les rôles passés en
 * argument, pour obtenir un utilisateur réellement admin ou non-admin.
 */
final class PrintGateDeviceControllerTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'contact@trievesconnect.fr';
    private const NON_ADMIN_EMAIL = 'membre.test@example.test';

    public function testIndexRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/printgate-device/');

        self::assertTrue($client->getResponse()->isRedirect());
    }

    public function testIndexRejectsNonAdminUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::NON_ADMIN_EMAIL));

        $client->request('GET', '/admin/printgate-device/');

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexAccessibleToAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $client->request('GET', '/admin/printgate-device/');

        self::assertResponseIsSuccessful();
    }

    public function testCreateDeviceWithPastedPublicKey(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $this->removeDeviceIfExists('POSTE-TEST-01');

        $client->request('GET', '/admin/printgate-device/create');
        self::assertResponseIsSuccessful();

        $client->submitForm('Enregistrer', [
            'print_gate_device[hostname]' => 'poste-test-01',
            'print_gate_device[computerId]' => 'POSTE-TEST-01',
            'print_gate_device[publicKeyText]' => "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\n-----END PUBLIC KEY-----",
        ]);

        self::assertResponseRedirects('/admin/printgate-device/');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $device = $entityManager->getRepository(PrintGateDevice::class)->findOneBy(['computerId' => 'POSTE-TEST-01']);

        self::assertNotNull($device);
        self::assertStringContainsString('BEGIN PUBLIC KEY', (string) $device->getPublicKey());
    }

    public function testCreateDeviceWithInvalidPublicKeyFormatIsRejected(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));
        $this->removeDeviceIfExists('POSTE-TEST-02');

        $client->request('GET', '/admin/printgate-device/create');

        $client->submitForm('Enregistrer', [
            'print_gate_device[hostname]' => 'poste-test-02',
            'print_gate_device[computerId]' => 'POSTE-TEST-02',
            'print_gate_device[publicKeyText]' => 'ceci n\'est pas une clé',
        ]);

        // AbstractController::render() (Symfony 7.4) place automatiquement
        // la réponse en 422 dès qu'un FormInterface soumis-mais-invalide
        // figure dans les paramètres passés à render() -- le formulaire est
        // réaffiché avec l'erreur, mais pas avec un statut 2xx.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Format de clé publique non reconnu');
    }

    /**
     * publicKeyText est mapped: false (upload OU collage) : sans
     * pré-remplissage explicite dans le contrôleur, ce champ apparaît
     * toujours vide en édition, donnant l'impression qu'une clé déjà
     * enregistrée ne l'a jamais été. Vérifie le pré-remplissage, et que
     * resoumettre le formulaire sans y toucher ne fait pas perdre la clé.
     */
    public function testEditFormPrefillsExistingPublicKeyAndKeepsItOnResubmit(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $this->removeDeviceIfExists('POSTE-TEST-EDIT-KEY');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $existingKey = "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\n-----END PUBLIC KEY-----";
        $device = new PrintGateDevice('POSTE-TEST-EDIT-KEY', 'poste-edit-key');
        $device->setPublicKey($existingKey);
        $entityManager->persist($device);
        $entityManager->flush();
        $deviceId = $device->getId();

        $crawler = $client->request('GET', '/admin/printgate-device/'.$deviceId.'/edit');

        self::assertSame(
            $existingKey,
            trim($crawler->filter('textarea[name="print_gate_device[publicKeyText]"]')->text(normalizeWhitespace: false)),
        );

        $client->submitForm('Enregistrer', [
            'print_gate_device[hostname]' => 'poste-edit-key-renamed',
        ]);

        self::assertResponseRedirects('/admin/printgate-device/');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $device = $entityManager->getRepository(PrintGateDevice::class)->find($deviceId);
        self::assertSame($existingKey, $device->getPublicKey());
    }

    public function testToggleEnabledFlipsStatus(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $this->removeDeviceIfExists('POSTE-TEST-TOGGLE');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $device = new PrintGateDevice('POSTE-TEST-TOGGLE', 'poste-toggle');
        $entityManager->persist($device);
        $entityManager->flush();
        $deviceId = $device->getId();

        self::assertTrue($device->isEnabled());

        $crawler = $client->request('GET', '/admin/printgate-device/');
        $token = $crawler->filter('#printgate-device-'.$deviceId.' input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/printgate-device/'.$deviceId.'/toggle', ['_token' => $token]);

        self::assertResponseRedirects('/admin/printgate-device/');

        // KernelBrowser reboot le kernel entre chaque requête par défaut :
        // l'EntityManager capturé avant les requêtes est obsolète, il faut
        // le réobtenir depuis le container pour lire l'état à jour.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $device = $entityManager->getRepository(PrintGateDevice::class)->find($deviceId);
        self::assertFalse($device->isEnabled());
    }

    public function testToggleEnabledRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $this->removeDeviceIfExists('POSTE-TEST-CSRF');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $device = new PrintGateDevice('POSTE-TEST-CSRF', 'poste-csrf');
        $entityManager->persist($device);
        $entityManager->flush();

        $client->request('POST', '/admin/printgate-device/'.$device->getId().'/toggle', ['_token' => 'jeton-invalide']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteRemovesDeviceAndItsUsedTokens(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $this->removeDeviceIfExists('POSTE-TEST-DELETE');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $device = new PrintGateDevice('POSTE-TEST-DELETE', 'poste-delete');
        $entityManager->persist($device);
        $entityManager->flush();
        $deviceId = $device->getId();

        // Un jeton anti-rejeu associé : vérifie que la suppression ne se
        // heurte pas à la contrainte de clé étrangère device_id (cf.
        // PrintGateUsedTokenRepository::deleteAllForDevice()).
        $entityManager->persist(new PrintGateUsedToken('jti-test-delete', $device, new \DateTimeImmutable('+1 hour')));
        $entityManager->flush();

        $crawler = $client->request('GET', '/admin/printgate-device/');
        $token = $crawler->filter('#printgate-device-'.$deviceId.' form[action$="/delete"] input[name="_token"]')->attr('value');

        $client->request('DELETE', '/admin/printgate-device/'.$deviceId.'/delete', ['_token' => $token]);

        self::assertResponseRedirects('/admin/printgate-device/');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(PrintGateDevice::class)->find($deviceId));
        self::assertSame([], $entityManager->getRepository(PrintGateUsedToken::class)->findBy(['device' => $deviceId]));
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildUser(self::ADMIN_EMAIL));

        $this->removeDeviceIfExists('POSTE-TEST-DELETE-CSRF');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $device = new PrintGateDevice('POSTE-TEST-DELETE-CSRF', 'poste-delete-csrf');
        $entityManager->persist($device);
        $entityManager->flush();

        $client->request('DELETE', '/admin/printgate-device/'.$device->getId().'/delete', ['_token' => 'jeton-invalide']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Base de test isolée (var/data_test.db, cf. .env.test) : un computerId
     * fixe réutilisé d'un run à l'autre viole la contrainte unique sans ce
     * nettoyage préalable.
     */
    private function removeDeviceIfExists(string $computerId): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $existing = $entityManager->getRepository(PrintGateDevice::class)->findOneBy(['computerId' => $computerId]);

        if (null !== $existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
        }
    }

    /**
     * Enregistre (ou réutilise) un utilisateur de test en base : la
     * "main" firewall n'est pas stateless, Symfony recharge l'utilisateur
     * depuis le provider (entity, cf. security.yaml) à chaque requête --
     * un objet User simplement construit en mémoire sans être persisté
     * serait introuvable et la session traitée comme anonyme.
     *
     * IMPORTANT : ne supprime jamais un utilisateur existant trouvé par cet
     * email -- même sur la base de test isolée, cf. incident du 2026-07-06
     * où ce test avait écrasé un compte "contact@trievesconnect.fr" réel
     * sur la base de dev partagée d'alors (mot de passe remplacé par une
     * valeur factice, sans sauvegarde possible). On réutilise l'existant
     * tel quel : ses rôles dépendent uniquement de son email
     * (cf. User::getRoles()), pas de son mot de passe.
     */
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
