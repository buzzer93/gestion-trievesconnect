<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Simplifie le forfait mairie : un montant unique en euros
 * (municipal_budget_settings.annual_allowance_cents) remplace le couple
 * annual_page_allowance / price_per_page_cents -- la mairie verse un
 * forfait global (50 € par défaut), pas un budget calculé à partir d'un
 * tarif à la page (cf. règles projet anti-surengineering).
 */
final class Version20260808140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace annual_page_allowance/price_per_page_cents par un montant unique annual_allowance_cents';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TEMPORARY TABLE __temp__municipal_budget_settings AS SELECT id, annual_page_allowance * price_per_page_cents AS annual_allowance_cents FROM municipal_budget_settings');
            $this->addSql('DROP TABLE municipal_budget_settings');
            $this->addSql('CREATE TABLE municipal_budget_settings (id INTEGER PRIMARY KEY NOT NULL, annual_allowance_cents INTEGER NOT NULL)');
            $this->addSql('INSERT INTO municipal_budget_settings (id, annual_allowance_cents) SELECT id, annual_allowance_cents FROM __temp__municipal_budget_settings');
            $this->addSql('DROP TABLE __temp__municipal_budget_settings');

            return;
        }

        $this->addSql('ALTER TABLE municipal_budget_settings ADD annual_allowance_cents INT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE municipal_budget_settings SET annual_allowance_cents = annual_page_allowance * price_per_page_cents');
        $this->addSql('ALTER TABLE municipal_budget_settings DROP annual_page_allowance, DROP price_per_page_cents');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TEMPORARY TABLE __temp__municipal_budget_settings AS SELECT id, annual_allowance_cents FROM municipal_budget_settings');
            $this->addSql('DROP TABLE municipal_budget_settings');
            $this->addSql('CREATE TABLE municipal_budget_settings (id INTEGER PRIMARY KEY NOT NULL, annual_page_allowance INTEGER NOT NULL, price_per_page_cents INTEGER NOT NULL)');
            $this->addSql("INSERT INTO municipal_budget_settings (id, annual_page_allowance, price_per_page_cents) SELECT id, 500, annual_allowance_cents / 500 FROM __temp__municipal_budget_settings");
            $this->addSql('DROP TABLE __temp__municipal_budget_settings');

            return;
        }

        $this->addSql('ALTER TABLE municipal_budget_settings ADD annual_page_allowance INT NOT NULL DEFAULT 500, ADD price_per_page_cents INT NOT NULL DEFAULT 10');
        $this->addSql('UPDATE municipal_budget_settings SET annual_page_allowance = 500, price_per_page_cents = annual_allowance_cents / 500');
        $this->addSql('ALTER TABLE municipal_budget_settings DROP annual_allowance_cents');
    }
}
