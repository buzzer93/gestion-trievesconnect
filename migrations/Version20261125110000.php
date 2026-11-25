<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Services hors impression (demande client du 2026-11-25) : Plastification
 * à 2 € et Reliure de dossier à 4 €, même prix pour les 3 grilles
 * (clients, associations, mairie). Activés dans la grille MUNICIPAL :
 * le crédit mairie d'une association peut les financer (décision du
 * 2026-11-25).
 *
 * Données uniquement, aucun changement de schéma : ce sont des lignes de
 * print_price_rate avec color_mode = 'SERVICE' (cf.
 * PrintPriceRate::COLOR_MODE_SERVICE). SQL compatible MySQL et SQLite.
 */
final class Version20261125110000 extends AbstractMigration
{
    private const SERVICES = ['PLASTIFICATION' => 200, 'RELIURE_DOSSIER' => 400];
    private const SCOPES = ['CLIENT', 'ASSOCIATION', 'MUNICIPAL'];

    public function getDescription(): string
    {
        return 'Ajoute les services Plastification (2 €) et Reliure de dossier (4 €) aux 3 grilles tarifaires';
    }

    public function up(Schema $schema): void
    {
        foreach (self::SCOPES as $scope) {
            foreach (self::SERVICES as $code => $priceCents) {
                $this->addSql(
                    'INSERT INTO print_price_rate (scope, color_mode, paper_size, price_cents, enabled) VALUES (?, ?, ?, ?, 1)',
                    [$scope, 'SERVICE', $code, $priceCents],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM print_price_rate WHERE color_mode = 'SERVICE' AND paper_size IN ('PLASTIFICATION', 'RELIURE_DOSSIER')",
        );
    }
}
