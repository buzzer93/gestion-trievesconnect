<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AssociationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Client association bénéficiant du forfait mairie (500 pages / an, payées
 * par la mairie, facturées au trimestre). Hérite de tout le comportement
 * de Customer (solde, recherche par téléphone, débit) via Single Table
 * Inheritance -- cf. Customer::class pour le mapping -- et ajoute
 * uniquement le crédit mairie, débité en priorité sur le crédit personnel
 * lors d'une impression (cf. PrintPolicyEvaluator).
 */
#[ORM\Entity(repositoryClass: AssociationRepository::class)]
class Association extends Customer
{
    #[ORM\Column]
    private int $municipalCredits = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $municipalCreditsRenewedAt = null;

    public function getMunicipalBalanceCents(): int
    {
        return $this->municipalCredits;
    }

    public function addMunicipalBalanceCents(int $cents): self
    {
        if ($cents > 0) {
            $this->municipalCredits += $cents;
        }

        return $this;
    }

    public function removeMunicipalBalanceCents(int $cents): self
    {
        if ($cents <= 0) {
            return $this;
        }
        if ($this->municipalCredits - $cents < 0) {
            trigger_error('Crédit mairie insuffisant', E_USER_WARNING);

            return $this;
        }
        $this->municipalCredits -= $cents;

        return $this;
    }

    public function setMunicipalBalanceCents(int $cents): self
    {
        $this->municipalCredits = max(0, $cents);

        return $this;
    }

    public function getMunicipalCreditsRenewedAt(): ?\DateTimeImmutable
    {
        return $this->municipalCreditsRenewedAt;
    }

    /**
     * Remise à zéro annuelle du crédit mairie, suivie du nouveau forfait
     * (cf. RenewMunicipalCreditsCommand). Le reliquat non consommé n'est
     * jamais reporté, conformément à la décision prise avec Damien.
     *
     * Le montant du forfait est passé en paramètre plutôt que codé en dur
     * ici, pour rester piloté par MunicipalBudgetSettings (page admin
     * "Budget mairie") sans que cette entité ait à en dépendre directement.
     */
    public function renewMunicipalCredits(\DateTimeImmutable $at, int $allowanceCents): self
    {
        $this->municipalCredits = max(0, $allowanceCents);
        $this->municipalCreditsRenewedAt = $at;

        return $this;
    }
}
