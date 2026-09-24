<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintPriceRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tarif d'impression pour une combinaison couleur/format, dans une
 * "portée" donnée (cf. $scope) -- remplace la grille unique partagée
 * client/association par 3 grilles distinctes (décision du 2026-08-25) :
 * - CLIENT : clients classiques ;
 * - ASSOCIATION : associations, financement personnel ;
 * - MUNICIPAL : associations, financement mairie -- prix propre (souvent
 *   inférieur au tarif ASSOCIATION) ET $enabled qui sert de règle
 *   d'éligibilité au financement municipal : une combinaison n'est
 *   utilisable par la mairie que si une ligne MUNICIPAL existe pour elle
 *   ET est activée. Pas de règle "A4 + N&B" codée en dur ailleurs dans le
 *   projet -- l'éligibilité est entièrement pilotée par cette donnée,
 *   modifiable depuis l'admin sans déploiement.
 */
#[ORM\Entity(repositoryClass: PrintPriceRateRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PRICE_RATE_SCOPE_TYPE', columns: ['scope', 'color_mode', 'paper_size'])]
class PrintPriceRate
{
    public const SCOPE_CLIENT = 'CLIENT';
    public const SCOPE_ASSOCIATION = 'ASSOCIATION';
    public const SCOPE_MUNICIPAL = 'MUNICIPAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $scope;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $colorMode;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $paperSize;

    #[ORM\Column(type: Types::INTEGER)]
    private int $priceCents;

    /**
     * Pour scope=MUNICIPAL : la mairie finance-t-elle réellement cette
     * combinaison couleur/format ? (cf. PHPDoc de classe). Pour
     * scope=CLIENT/ASSOCIATION : permet de désactiver temporairement une
     * combinaison sans la supprimer (même sémantique "pas de tarif
     * applicable" que $enabled=false côté mairie).
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled;

    public function __construct(string $scope, string $colorMode, string $paperSize, int $priceCents, bool $enabled = true)
    {
        $this->scope = $scope;
        $this->colorMode = $colorMode;
        $this->paperSize = $paperSize;
        $this->priceCents = $priceCents;
        $this->enabled = $enabled;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getColorMode(): string
    {
        return $this->colorMode;
    }

    public function getPaperSize(): string
    {
        return $this->paperSize;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): self
    {
        $this->priceCents = max(0, $priceCents);

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }
}
