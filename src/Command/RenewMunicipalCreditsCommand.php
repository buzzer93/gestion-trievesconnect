<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AssociationRepository;
use App\Repository\MunicipalBudgetSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renouvellement annuel du forfait mairie (montant configurable, cf. page
 * admin "Budget mairie" / MunicipalBudgetSettings, 500 pages à 0,10 € par
 * défaut) pour toutes les associations. Remet le solde mairie au montant
 * du forfait, sans jamais reporter le reliquat non consommé -- décision
 * prise avec l'utilisateur : le forfait fonctionne en "usage ou perte",
 * pas de cumul d'une année sur l'autre.
 *
 * À planifier en cron le 1er janvier :
 *     0 0 1 1 * php bin/console printgate:renew-municipal-credits
 */
#[AsCommand(
    name: 'printgate:renew-municipal-credits',
    description: 'Renouvelle le forfait mairie annuel de toutes les associations',
)]
class RenewMunicipalCreditsCommand extends Command
{
    public function __construct(
        private readonly AssociationRepository $associationRepository,
        private readonly MunicipalBudgetSettingsRepository $budgetSettingsRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $allowanceCents = $this->budgetSettingsRepository->getSettings()->getAnnualAllowanceCents();

        $associations = $this->associationRepository->findAll();

        foreach ($associations as $association) {
            $association->renewMunicipalCredits($now, $allowanceCents);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d association(s) renouvelée(s) avec %s € de forfait mairie.',
            count($associations),
            number_format($allowanceCents / 100, 2, ',', ''),
        ));

        return Command::SUCCESS;
    }
}
