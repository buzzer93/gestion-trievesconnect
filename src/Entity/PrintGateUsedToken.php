<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintGateUsedTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace des `jti` déjà consommés, pour l'anti-rejeu JWT (résumé technique
 * §10). Table dédiée plutôt que le seul cache Symfony : convient telle
 * quelle en instance unique, et reste correcte si le site est un jour
 * déployé derrière plusieurs workers (contrainte unique en base, pas
 * seulement en mémoire locale).
 *
 * `expiresAt` sert uniquement au nettoyage (cf. commande
 * printgate:cleanup-used-tokens, à ajouter) -- pas à la détection du
 * rejeu elle-même, qui repose sur la contrainte unique de `jti`.
 */
#[ORM\Entity(repositoryClass: PrintGateUsedTokenRepository::class)]
#[ORM\Table(name: 'print_gate_used_token')]
class PrintGateUsedToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 190, unique: true)]
    private string $jti;

    #[ORM\ManyToOne(targetEntity: PrintGateDevice::class)]
    #[ORM\JoinColumn(nullable: false)]
    private PrintGateDevice $device;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $usedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $jti, PrintGateDevice $device, \DateTimeImmutable $expiresAt)
    {
        $this->jti = $jti;
        $this->device = $device;
        $this->usedAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJti(): string
    {
        return $this->jti;
    }

    public function getDevice(): PrintGateDevice
    {
        return $this->device;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
