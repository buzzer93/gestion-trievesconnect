<?php

namespace App\Service;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;


class DatabaseResetService
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function resetDatabase(): string
    {
        // Récupération de l'application console
        $application = new Application($this->kernel);
        $application->setAutoExit(false); // Empêche l'application de se terminer après exécution

        // Configuration des arguments pour la commande
        $input = new ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--env' => 'dev', // Changez selon votre environnement
        ]);

        $output = new BufferedOutput();

        // Exécution de la commande
        $application->run($input, $output);

        // Retourne la sortie pour inspection ou logging
        return $output->fetch();
    }
}