<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nouveau grand livre des impressions débitées (print_transaction /
 * print_transaction_line), remplace PrintMunicipalConsumption pour toute
 * nouvelle écriture -- couvre désormais aussi les clients classiques.
 *
 * IMPORTANT : print_municipal_consumption N'EST PAS supprimée ni migrée
 * ici. C'est une décision délibérée, pas un oubli : elle sert de
 * justificatif pour des factures mairie déjà émises sur les trimestres
 * passés, et les règles de tarification ont changé (tarif mairie propre,
 * désormais différent du tarif association) -- une migration automatique
 * des anciennes lignes vers le nouveau modèle produirait des montants
 * reconstruits, pas les montants réellement appliqués à l'époque.
 * MunicipalBudgetController interroge les deux tables et fusionne
 * (transition), print_municipal_consumption reste lisible mais n'est plus
 * jamais écrite après cette migration.
 *
 * La contrainte UNIQUE (print_gate_device_id, job_id) sur print_transaction
 * porte l'idempotence PrintGate : NULL n'entre jamais en conflit avec NULL
 * (MySQL comme SQLite), donc aucune limite sur les débits manuels admin
 * (poste et jobId à NULL pour ceux-ci).
 */
final class Version20260825170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée print_transaction et print_transaction_line (grand livre idempotent des débits d\'impression)';
    }

    public function up(Schema $schema): void
    {
        if ('sqlite' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql(<<<'SQL'
                CREATE TABLE print_transaction (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    reference VARCHAR(26) NOT NULL,
                    customer_id INTEGER NOT NULL,
                    print_gate_device_id INTEGER DEFAULT NULL,
                    job_id INTEGER DEFAULT NULL,
                    color_mode VARCHAR(32) DEFAULT NULL,
                    paper_size VARCHAR(32) DEFAULT NULL,
                    page_count INTEGER NOT NULL,
                    duplex_mode VARCHAR(32) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    created_by_id INTEGER DEFAULT NULL,
                    motif VARCHAR(255) DEFAULT NULL,
                    CONSTRAINT FK_PRINT_TX_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                    CONSTRAINT FK_PRINT_TX_DEVICE FOREIGN KEY (print_gate_device_id) REFERENCES print_gate_device (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                    CONSTRAINT FK_PRINT_TX_USER FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE
                )
                SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_PRINT_TX_REFERENCE ON print_transaction (reference)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_PRINT_TX_DEVICE_JOB ON print_transaction (print_gate_device_id, job_id)');
            $this->addSql('CREATE INDEX IDX_PRINT_TX_CUSTOMER ON print_transaction (customer_id)');

            $this->addSql(<<<'SQL'
                CREATE TABLE print_transaction_line (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    transaction_id INTEGER NOT NULL,
                    funding_source VARCHAR(32) NOT NULL,
                    copies INTEGER NOT NULL,
                    unit_price_cents INTEGER NOT NULL,
                    amount_cents INTEGER NOT NULL,
                    CONSTRAINT FK_PRINT_TX_LINE_TRANSACTION FOREIGN KEY (transaction_id) REFERENCES print_transaction (id) NOT DEFERRABLE INITIALLY IMMEDIATE
                )
                SQL);
            $this->addSql('CREATE INDEX IDX_PRINT_TX_LINE_TRANSACTION ON print_transaction_line (transaction_id)');

            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE print_transaction (
                id INT AUTO_INCREMENT NOT NULL,
                reference VARCHAR(26) NOT NULL,
                customer_id INT NOT NULL,
                print_gate_device_id INT DEFAULT NULL,
                job_id INT DEFAULT NULL,
                color_mode VARCHAR(32) DEFAULT NULL,
                paper_size VARCHAR(32) DEFAULT NULL,
                page_count INT NOT NULL,
                duplex_mode VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                created_by_id INT DEFAULT NULL,
                motif VARCHAR(255) DEFAULT NULL,
                UNIQUE INDEX UNIQ_PRINT_TX_REFERENCE (reference),
                UNIQUE INDEX UNIQ_PRINT_TX_DEVICE_JOB (print_gate_device_id, job_id),
                INDEX IDX_PRINT_TX_CUSTOMER (customer_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE print_transaction_line (
                id INT AUTO_INCREMENT NOT NULL,
                transaction_id INT NOT NULL,
                funding_source VARCHAR(32) NOT NULL,
                copies INT NOT NULL,
                unit_price_cents INT NOT NULL,
                amount_cents INT NOT NULL,
                INDEX IDX_PRINT_TX_LINE_TRANSACTION (transaction_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql('ALTER TABLE print_transaction ADD CONSTRAINT FK_PRINT_TX_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id)');
        // SET NULL : la suppression d'un poste PrintGate ne doit jamais faire perdre l'historique financier (cf. PrintTransaction::$printGateDevice).
        $this->addSql('ALTER TABLE print_transaction ADD CONSTRAINT FK_PRINT_TX_DEVICE FOREIGN KEY (print_gate_device_id) REFERENCES print_gate_device (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE print_transaction ADD CONSTRAINT FK_PRINT_TX_USER FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE print_transaction_line ADD CONSTRAINT FK_PRINT_TX_LINE_TRANSACTION FOREIGN KEY (transaction_id) REFERENCES print_transaction (id)');
    }

    public function down(Schema $schema): void
    {
        if ('sqlite' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('DROP TABLE print_transaction_line');
            $this->addSql('DROP TABLE print_transaction');

            return;
        }

        $this->addSql('ALTER TABLE print_transaction_line DROP FOREIGN KEY FK_PRINT_TX_LINE_TRANSACTION');
        $this->addSql('ALTER TABLE print_transaction DROP FOREIGN KEY FK_PRINT_TX_CUSTOMER');
        $this->addSql('ALTER TABLE print_transaction DROP FOREIGN KEY FK_PRINT_TX_DEVICE');
        $this->addSql('ALTER TABLE print_transaction DROP FOREIGN KEY FK_PRINT_TX_USER');
        $this->addSql('DROP TABLE print_transaction_line');
        $this->addSql('DROP TABLE print_transaction');
    }
}
