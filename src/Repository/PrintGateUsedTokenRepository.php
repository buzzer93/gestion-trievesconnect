<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PrintGateDevice;
use App\Entity\PrintGateUsedToken;
use App\Security\PrintGate\Exception\PrintGateReplayException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrintGateUsedToken>
 */
class PrintGateUsedTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrintGateUsedToken::class);
    }

    /**
     * Enregistre le `jti` comme consommé. La détection du rejeu repose sur
     * la contrainte unique en base (pas sur un test préalable en lecture,
     * qui laisserait une fenêtre de course entre deux requêtes concurrentes
     * portant le même jti) : on tente l'insertion et on traduit une
     * violation de contrainte en PrintGateReplayException.
     *
     * @throws PrintGateReplayException si ce jti a déjà été consommé
     */
    public function markAsUsed(string $jti, PrintGateDevice $device, \DateTimeImmutable $expiresAt): void
    {
        $entityManager = $this->getEntityManager();

        try {
            $entityManager->persist(new PrintGateUsedToken($jti, $device, $expiresAt));
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new PrintGateReplayException(\sprintf('jti "%s" déjà utilisé', $jti));
        }
    }

    /**
     * Supprime l'historique anti-rejeu d'un poste avant sa suppression
     * (contrainte de clé étrangère non-nullable device_id). Suppression en
     * masse via DQL plutôt qu'un chargement en mémoire : ces lignes ne sont
     * jamais consultées individuellement à la suppression d'un poste.
     */
    public function deleteAllForDevice(PrintGateDevice $device): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.device = :device')
            ->setParameter('device', $device)
            ->getQuery()
            ->execute();
    }
}
