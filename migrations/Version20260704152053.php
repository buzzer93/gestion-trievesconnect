<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table print_gate_device (module PrintGate - étape 4).
 */
final class Version20260704152053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table print_gate_device (PrintGate - étape 4)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TABLE print_gate_device (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, computer_id VARCHAR(190) NOT NULL, hostname VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, public_key CLOB DEFAULT NULL, enabled BOOLEAN NOT NULL, last_seen_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_ADCBAC7EA426D518 ON print_gate_device (computer_id)');

            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE print_gate_device (
                id INT AUTO_INCREMENT NOT NULL,
                computer_id VARCHAR(190) NOT NULL,
                hostname VARCHAR(255) NOT NULL,
                display_name VARCHAR(255) DEFAULT NULL,
                public_key LONGTEXT DEFAULT NULL,
                enabled TINYINT(1) NOT NULL,
                last_seen_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_ADCBAC7EA426D518 (computer_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE print_gate_device');
    }
}
