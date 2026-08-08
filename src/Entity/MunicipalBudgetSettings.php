<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglage unique (singleton, id=1) du forfait mairie : montant crédité en
 * euros aux associations à chaque renouvellement (cf.
 * RenewMunicipalCreditsCommand). Remplace
 * Association::ANNUAL_MUNICIPAL_ALLOWANCE_CENTS, auparavant codé en dur,
 * pour le rendre modifiable depuis l'admin (page "Budget mairie").
 *
 * Un simple montant en euros plutôt qu'un calcul pages x prix/page : la
 * mairie verse un forfait global (50 € par défaut), pas un budget dérivé
 * d'un tarif à la page (cf. règles projet anti-surengineering -- pas de
 * champs supplémentaires sans besoin métier réel).
 */
#[ORM\Entity]
class MunicipalBudgetSettings
{
    public const SINGLETON_ID = 1;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column(type: Types::INTEGER)]
    private int $annualAllowanceCents = 5000;

    public function getId(): int
    {
        return $this->id;
    }

    public function getAnnualAllowanceCents(): int
    {
        return $this->annualAllowanceCents;
    }

    public function setAnnualAllowanceCents(int $cents): self
    {
        $this->annualAllowanceCents = max(0, $cents);

        return $this;
    }
}
