<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglage unique (singleton, id=1) du forfait mairie : nombre de pages
 * annuel et prix par page, dont le produit donne le montant crédité aux
 * associations à chaque renouvellement (cf. RenewMunicipalCreditsCommand).
 * Remplace Association::ANNUAL_MUNICIPAL_ALLOWANCE_CENTS, auparavant codé
 * en dur, pour le rendre modifiable depuis l'admin (page "Budget mairie").
 */
#[ORM\Entity]
class MunicipalBudgetSettings
{
    public const SINGLETON_ID = 1;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column(type: Types::INTEGER)]
    private int $annualPageAllowance = 500;

    #[ORM\Column(type: Types::INTEGER)]
    private int $pricePerPageCents = 10;

    public function getId(): int
    {
        return $this->id;
    }

    public function getAnnualPageAllowance(): int
    {
        return $this->annualPageAllowance;
    }

    public function setAnnualPageAllowance(int $pages): self
    {
        $this->annualPageAllowance = max(0, $pages);

        return $this;
    }

    public function getPricePerPageCents(): int
    {
        return $this->pricePerPageCents;
    }

    public function setPricePerPageCents(int $cents): self
    {
        $this->pricePerPageCents = max(0, $cents);

        return $this;
    }

    public function getAnnualAllowanceCents(): int
    {
        return $this->annualPageAllowance * $this->pricePerPageCents;
    }
}
