<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\PrintGateDevice;
use App\Entity\PrintGateUsedToken;
use App\Repository\PrintGateUsedTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Couvre deleteExpiredBefore()/countExpiredBefore() (commande
 * printgate:cleanup-used-tokens) : seuls les jetons dont expiresAt est
 * strictement dans le passé par rapport à la borne doivent être comptés/
 * supprimés, jamais ceux encore valides.
 */
final class PrintGateUsedTokenRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PrintGateUsedTokenRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(PrintGateUsedTokenRepository::class);
    }

    public function testDeleteExpiredBeforeOnlyRemovesExpiredTokens(): void
    {
        $device = $this->buildDevice('POSTE-CLEANUP-01');
        $now = new \DateTimeImmutable();

        // Delta plutôt que valeur absolue : la base de test accumule des
        // jetons créés par d'autres tests PrintGate au fil des runs
        // (fichier SQLite persistant, jamais réinitialisé entre exécutions),
        // donc le nombre total de jetons expirés n'est jamais prévisible.
        $baseline = $this->repository->countExpiredBefore($now);

        $expired1 = $this->buildToken($device, $now->modify('-2 hours'));
        $expired2 = $this->buildToken($device, $now->modify('-1 minute'));
        $stillValid = $this->buildToken($device, $now->modify('+1 hour'));

        self::assertSame($baseline + 2, $this->repository->countExpiredBefore($now));

        [$expired1Id, $expired2Id, $stillValidId] = [$expired1->getId(), $expired2->getId(), $stillValid->getId()];

        $deleted = $this->repository->deleteExpiredBefore($now);

        self::assertSame($baseline + 2, $deleted);

        // deleteExpiredBefore() supprime en masse via DQL (cf. son PHPDoc),
        // ce qui contourne l'identity map : sans clear(), find() renverrait
        // les objets encore en mémoire au lieu de re-vérifier la base.
        $this->em->clear();

        self::assertNull($this->repository->find($expired1Id));
        self::assertNull($this->repository->find($expired2Id));
        self::assertNotNull($this->repository->find($stillValidId));
        self::assertSame(0, $this->repository->countExpiredBefore($now));
    }

    public function testDeleteExpiredBeforeIsNoOpWhenNothingExpired(): void
    {
        $device = $this->buildDevice('POSTE-CLEANUP-02');
        $now = new \DateTimeImmutable();
        $stillValid = $this->buildToken($device, $now->modify('+30 minutes'));

        // Purge d'abord ce que d'autres tests ont pu laisser d'expiré,
        // pour isoler le comportement "rien à faire" vérifié ici (cf.
        // commentaire ci-dessus sur la base de test partagée).
        $this->repository->deleteExpiredBefore($now);

        $deleted = $this->repository->deleteExpiredBefore($now);

        self::assertSame(0, $deleted);
        self::assertNotNull($this->repository->find($stillValid->getId()));
    }

    /**
     * Réutilise le poste existant plutôt que d'en recréer un -- la base de
     * test SQLite persiste entre les exécutions (jamais réinitialisée),
     * donc un `computer_id` déjà utilisé par un run précédent ferait
     * échouer l'insertion sur la contrainte UNIQUE (même raison que
     * PrintAuthorizationControllerTest::registerTestDevice()).
     */
    private function buildDevice(string $computerId): PrintGateDevice
    {
        $existing = $this->em->getRepository(PrintGateDevice::class)->findOneBy(['computerId' => $computerId]);
        if (null !== $existing) {
            return $existing;
        }

        $device = new PrintGateDevice($computerId, $computerId . '-hostname');
        $this->em->persist($device);
        $this->em->flush();

        return $device;
    }

    private function buildToken(PrintGateDevice $device, \DateTimeImmutable $expiresAt): PrintGateUsedToken
    {
        $token = new PrintGateUsedToken(bin2hex(random_bytes(8)), $device, $expiresAt);
        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }
}
