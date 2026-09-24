<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PrintGateUsedTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge la table print_gate_used_token des jetons dont `expiresAt` est
 * dépassé -- reste à faire signalé depuis l'étape 5 (sécurité JWT) du
 * module PrintGate, jamais ajouté jusqu'ici.
 *
 * Sans danger pour l'anti-rejeu : un jti expiré est de toute façon rejeté
 * par PrintGateJwtVerifier (vérification `exp`) avant même d'atteindre la
 * table -- le supprimer ne réouvre donc aucune fenêtre de rejeu. Cette
 * table croît sinon indéfiniment (un jti par job imprimé).
 *
 * Destinée à un déclenchement périodique (cron), contrairement à
 * printgate:renew-municipal-credits qui est volontairement manuel : ajouter
 * une entrée crontab sur le VPS, ex. tous les jours à 3h :
 *   0 3 * * * cd /var/www/mon-projet && php bin/console printgate:cleanup-used-tokens --quiet
 */
#[AsCommand(
    name: 'printgate:cleanup-used-tokens',
    description: 'Purge les jetons PrintGate anti-rejeu expirés (à planifier en cron)',
)]
class CleanupPrintGateUsedTokensCommand extends Command
{
    public function __construct(
        private readonly PrintGateUsedTokenRepository $usedTokenRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Compte les jetons expirés sans les supprimer',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        if ($input->getOption('dry-run')) {
            $count = $this->usedTokenRepository->countExpiredBefore($now);
            $io->info(sprintf('%d jeton(s) PrintGate expiré(s) seraient supprimés (--dry-run, rien de fait).', $count));

            return Command::SUCCESS;
        }

        $deleted = $this->usedTokenRepository->deleteExpiredBefore($now);

        $io->success(sprintf('%d jeton(s) PrintGate expiré(s) supprimé(s).', $deleted));

        return Command::SUCCESS;
    }
}
