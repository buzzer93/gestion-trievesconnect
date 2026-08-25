<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AssociationRepository;
use App\Repository\MunicipalBudgetSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Renouvellement du forfait mairie (montant configurable, page admin
 * "Budget mairie") pour toutes les associations. Remet le solde mairie au
 * montant du forfait, sans jamais reporter le reliquat non consommé --
 * décision prise avec l'utilisateur : le forfait fonctionne en "usage ou
 * perte", pas de cumul d'une année sur l'autre.
 *
 * Utilisé à la fois par la commande CLI (printgate:renew-municipal-credits,
 * conservée pour un usage scripté) et par le bouton "Recharger maintenant"
 * de la page admin -- décision du 2026-08-25 : le déclenchement reste
 * manuel (l'admin choisit le moment), pas un cron automatique au 1er
 * janvier. Un seul endroit pour la logique, pas de duplication entre les
 * deux points d'entrée.
 */
final class MunicipalCreditsRenewalService
{
    public function __construct(
        private readonly AssociationRepository $associationRepository,
        private readonly MunicipalBudgetSettingsRepository $budgetSettingsRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int nombre d'associations renouvelées
     */
    public function renewAll(): int
    {
        $allowanceCents = $this->budgetSettingsRepository->getSettings()->getAnnualAllowanceCents();
        $associations = $this->associationRepository->findAll();
        $now = new \DateTimeImmutable();

        foreach ($associations as $association) {
            $association->renewMunicipalCredits($now, $allowanceCents);
        }

        $this->entityManager->flush();

        return count($associations);
    }
}
