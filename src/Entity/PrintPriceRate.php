<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrintPriceRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tarif d'impression pour une combinaison couleur/format, configurable
 * depuis l'admin (page "Tarifs d'impression") -- remplace la grille
 * PRICES_CENTS auparavant codée en dur dans PrintPolicyEvaluator. Le
 * nombre de combinaisons reste fixe (couleur x format), gérées en une
 * seule page de réglages plutôt qu'en CRUD libre : pas de sens métier à
 * créer une combinaison arbitraire supplémentaire.
 */
#[ORM\Entity(repositoryClass: PrintPriceRateRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PRICE_RATE_TYPE', columns: ['color_mode', 'paper_size'])]
class PrintPriceRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $colorMode;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $paperSize;

    #[ORM\Column(type: Types::INTEGER)]
    private int $priceCents;

    public function __construct(string $colorMode, string $paperSize, int $priceCents)
    {
        $this->colorMode = $colorMode;
        $this->paperSize = $paperSize;
        $this->priceCents = $priceCents;
    }

    public function getId(): ?int
    {
        return $this->id;
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
}
