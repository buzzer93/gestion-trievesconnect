<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintGateDeviceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Poste Linux autorisé à interroger l'API PrintGate.
 *
 * `publicKey` est nullable à ce stade (étape 4 : module minimal) : elle
 * deviendra obligatoire et vérifiée à l'étape 5 (sécurité JWT). Ne pas
 * ajouter ici de logique liée au JWT ou aux règles d'autorisation
 * (cf. PrintGateJwtVerifier / PrintPolicyEvaluator, étapes suivantes).
 */
#[ORM\Entity(repositoryClass: PrintGateDeviceRepository::class)]
#[ORM\Table(name: 'print_gate_device')]
class PrintGateDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 190, unique: true)]
    private string $computerId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $hostname;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $publicKey = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $computerId, string $hostname)
    {
        $this->computerId = $computerId;
        $this->hostname = $hostname;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComputerId(): string
    {
        return $this->computerId;
    }

    public function setComputerId(string $computerId): static
    {
        $this->computerId = $computerId;

        return $this;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function setHostname(string $hostname): static
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function setPublicKey(?string $publicKey): static
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        $this->touch();

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    /**
     * Marque le poste comme vu par l'API. À appeler dès qu'un
     * PrintGateDevice a été identifié et retrouvé actif, indépendamment
     * de la décision finale d'autorisation (cf. étape 6 : "vu" ne veut
     * pas dire "a obtenu une impression").
     */
    public function markAsSeen(\DateTimeImmutable $at): static
    {
        $this->lastSeenAt = $at;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
