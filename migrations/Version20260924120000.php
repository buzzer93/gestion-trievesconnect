<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Annulation logique des impressions (cf. PrintTransaction::$cancelledAt) :
 * colonnes nullables uniquement, aucune donnée existante modifiée -- toutes
 * les transactions déjà enregistrées restent non annulées.
 */
final class Version20260924120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l\'annulation logique des impressions (print_transaction.cancelled_at/cancelled_by_id/cancellation_reason)';
    }

    public function up(Schema $schema): void
    {
        if ('sqlite' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('ALTER TABLE print_transaction ADD COLUMN cancelled_at DATETIME DEFAULT NULL');
            $this->addSql('ALTER TABLE print_transaction ADD COLUMN cancelled_by_id INTEGER DEFAULT NULL REFERENCES user (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE print_transaction ADD COLUMN cancellation_reason VARCHAR(255) DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_PRINT_TX_CANCELLED_BY ON print_transaction (cancelled_by_id)');

            return;
        }

        $this->addSql('ALTER TABLE print_transaction ADD cancelled_at DATETIME DEFAULT NULL, ADD cancelled_by_id INT DEFAULT NULL, ADD cancellation_reason VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PRINT_TX_CANCELLED_BY ON print_transaction (cancelled_by_id)');
        $this->addSql('ALTER TABLE print_transaction ADD CONSTRAINT FK_PRINT_TX_CANCELLED_BY FOREIGN KEY (cancelled_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        if ('sqlite' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('DROP INDEX IDX_PRINT_TX_CANCELLED_BY');
            $this->addSql('ALTER TABLE print_transaction DROP COLUMN cancellation_reason');
            $this->addSql('ALTER TABLE print_transaction DROP COLUMN cancelled_by_id');
            $this->addSql('ALTER TABLE print_transaction DROP COLUMN cancelled_at');

            return;
        }

        $this->addSql('ALTER TABLE print_transaction DROP FOREIGN KEY FK_PRINT_TX_CANCELLED_BY');
        $this->addSql('DROP INDEX IDX_PRINT_TX_CANCELLED_BY ON print_transaction');
        $this->addSql('ALTER TABLE print_transaction DROP cancelled_at, DROP cancelled_by_id, DROP cancellation_reason');
    }
}
