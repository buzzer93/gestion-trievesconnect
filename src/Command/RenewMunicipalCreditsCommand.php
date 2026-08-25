<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MunicipalCreditsRenewalService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renouvellement du forfait mairie (montant configurable, cf. page admin
 * "Budget mairie" / MunicipalBudgetSettings) pour toutes les associations.
 *
 * Déclenchement volontairement manuel (décision du 2026-08-25) : ni cette
 * commande ni le bouton "Recharger maintenant" de la page admin ne sont
 * automatiques -- c'est l'admin qui choisit quand renouveler, typiquement
 * une fois par an en janvier, mais rien ne l'impose. Cette commande reste
 * disponible pour un usage scripté si besoin, mais n'est pas planifiée en
 * cron. La logique elle-même vit dans MunicipalCreditsRenewalService,
 * partagée avec le bouton admin -- pas de duplication.
 */
#[AsCommand(
    name: 'printgate:renew-municipal-credits',
    description: 'Renouvelle le forfait mairie de toutes les associations (déclenchement manuel)',
)]
class RenewMunicipalCreditsCommand extends Command
{
    public function __construct(
        private readonly MunicipalCreditsRenewalService $renewalService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->renewalService->renewAll();

        $io->success(sprintf('%d association(s) renouvelée(s).', $count));

        return Command::SUCCESS;
    }
}
