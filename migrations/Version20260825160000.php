<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remplace la grille tarifaire unique (partagée client/association) par 3
 * grilles distinctes (décision du 2026-08-25) : CLIENT, ASSOCIATION
 * (financement personnel de l'association) et MUNICIPAL (financement
 * mairie, avec activation par combinaison couleur/format -- sert de règle
 * d'éligibilité, cf. PrintPriceRate::$enabled).
 *
 * Valeurs de départ (à ajuster depuis l'admin, aucune n'est définitive) :
 * - CLIENT : reprend les 4 tarifs existants tels quels ;
 * - ASSOCIATION : mêmes montants que CLIENT au départ (aucune indication
 *   contraire au moment de cette migration) ;
 * - MUNICIPAL : seul MONOCHROME/A4 est activé (10 centimes), les 3 autres
 *   combinaisons sont créées désactivées à 0 -- prêtes à activer depuis
 *   l'admin sans nouvelle migration.
 */
final class Version20260825160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grille tarifaire à 3 portées (CLIENT/ASSOCIATION/MUNICIPAL) sur print_price_rate';
    }

    public function up(Schema $schema): void
    {
        $isSqlite = 'sqlite' === $this->connection->getDatabasePlatform()->getName();

        if ($isSqlite) {
            $this->addSql('ALTER TABLE print_price_rate ADD COLUMN scope VARCHAR(16) NOT NULL DEFAULT \'CLIENT\'');
            $this->addSql('ALTER TABLE print_price_rate ADD COLUMN enabled BOOLEAN NOT NULL DEFAULT 1');
            $this->addSql('DROP INDEX UNIQ_PRICE_RATE_TYPE');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_PRICE_RATE_SCOPE_TYPE ON print_price_rate (scope, color_mode, paper_size)');
        } else {
            $this->addSql("ALTER TABLE print_price_rate ADD scope VARCHAR(16) NOT NULL DEFAULT 'CLIENT', ADD enabled TINYINT(1) NOT NULL DEFAULT 1");
            $this->addSql('ALTER TABLE print_price_rate DROP INDEX UNIQ_PRICE_RATE_TYPE');
            $this->addSql('ALTER TABLE print_price_rate ADD UNIQUE INDEX UNIQ_PRICE_RATE_SCOPE_TYPE (scope, color_mode, paper_size)');
        }

        // Les 4 lignes existantes sont déjà scope='CLIENT' via le DEFAULT ci-dessus.

        // ASSOCIATION : copie des tarifs CLIENT actuels, point de départ neutre.
        $this->addSql("INSERT INTO print_price_rate (scope, color_mode, paper_size, price_cents, enabled)
            SELECT 'ASSOCIATION', color_mode, paper_size, price_cents, 1 FROM print_price_rate WHERE scope = 'CLIENT'");

        // MUNICIPAL : seul MONOCHROME/A4 activé.
        $this->addSql("INSERT INTO print_price_rate (scope, color_mode, paper_size, price_cents, enabled) VALUES ('MUNICIPAL', 'MONOCHROME', 'A4', 10, 1)");
        $this->addSql("INSERT INTO print_price_rate (scope, color_mode, paper_size, price_cents, enabled) VALUES ('MUNICIPAL', 'MONOCHROME', 'A3', 0, 0)");
        $this->addSql("INSERT INTO print_price_rate (scope, color_mode, paper_size, price_cents, enabled) VALUES ('MUNICIPAL', 'COLOR', 'A4', 0, 0)");
        $this->addSql("INSERT INTO print_price_rate (scope, color_mode, paper_size, price_cents, enabled) VALUES ('MUNICIPAL', 'COLOR', 'A3', 0, 0)");
    }

    public function down(Schema $schema): void
    {
        $isSqlite = 'sqlite' === $this->connection->getDatabasePlatform()->getName();

        $this->addSql("DELETE FROM print_price_rate WHERE scope IN ('ASSOCIATION', 'MUNICIPAL')");

        if ($isSqlite) {
            $this->addSql('DROP INDEX UNIQ_PRICE_RATE_SCOPE_TYPE');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_PRICE_RATE_TYPE ON print_price_rate (color_mode, paper_size)');
            $this->addSql('ALTER TABLE print_price_rate DROP COLUMN enabled');
            $this->addSql('ALTER TABLE print_price_rate DROP COLUMN scope');
        } else {
            $this->addSql('ALTER TABLE print_price_rate DROP INDEX UNIQ_PRICE_RATE_SCOPE_TYPE');
            $this->addSql('ALTER TABLE print_price_rate ADD UNIQUE INDEX UNIQ_PRICE_RATE_TYPE (color_mode, paper_size)');
            $this->addSql('ALTER TABLE print_price_rate DROP enabled, DROP scope');
        }
    }
}
