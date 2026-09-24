<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute print_municipal_consumption.funding_source ('MUNICIPAL' ou
 * 'PERSONAL') : cette table journalise désormais aussi les impressions
 * payées sur le crédit personnel d'une association, pas seulement le
 * crédit mairie, pour alimenter l'historique complet de la fiche
 * association (cf. AssociationRepository::debitForPrintJob()). Les lignes
 * déjà existantes ne concernaient que le crédit mairie -> backfill à
 * 'MUNICIPAL'.
 */
final class Version20260808150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute print_municipal_consumption.funding_source (MUNICIPAL/PERSONAL)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE print_municipal_consumption ADD COLUMN funding_source VARCHAR(16) NOT NULL DEFAULT 'MUNICIPAL'");
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TEMPORARY TABLE __temp__print_municipal_consumption AS SELECT id, association_id, print_job_id, created_at, page_count, copies, color_mode, paper_size, amount_spent_cents FROM print_municipal_consumption');
            $this->addSql('DROP TABLE print_municipal_consumption');
            $this->addSql('CREATE TABLE print_municipal_consumption (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, association_id INTEGER NOT NULL, print_job_id INTEGER NOT NULL, created_at DATETIME NOT NULL, page_count INTEGER NOT NULL, copies INTEGER NOT NULL, color_mode VARCHAR(32) DEFAULT NULL, paper_size VARCHAR(32) DEFAULT NULL, amount_spent_cents INTEGER NOT NULL, CONSTRAINT FK_PMC_ASSOCIATION FOREIGN KEY (association_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_PMC_ASSOCIATION ON print_municipal_consumption (association_id)');
            $this->addSql('INSERT INTO print_municipal_consumption (id, association_id, print_job_id, created_at, page_count, copies, color_mode, paper_size, amount_spent_cents) SELECT id, association_id, print_job_id, created_at, page_count, copies, color_mode, paper_size, amount_spent_cents FROM __temp__print_municipal_consumption');
            $this->addSql('DROP TABLE __temp__print_municipal_consumption');

            return;
        }

        $this->addSql('ALTER TABLE print_municipal_consumption DROP funding_source');
    }
}
