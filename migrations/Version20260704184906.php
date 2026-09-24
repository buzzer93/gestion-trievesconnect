<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table print_gate_used_token (module PrintGate - étape 5, anti-rejeu JWT).
 */
final class Version20260704184906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table print_gate_used_token (PrintGate - étape 5, anti-rejeu JWT)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TABLE print_gate_used_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, device_id INTEGER NOT NULL, jti VARCHAR(190) NOT NULL, used_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, CONSTRAINT FK_C585928F94A4C7D4 FOREIGN KEY (device_id) REFERENCES print_gate_device (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C585928FC53CF2EA ON print_gate_used_token (jti)');
            $this->addSql('CREATE INDEX IDX_C585928F94A4C7D4 ON print_gate_used_token (device_id)');

            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE print_gate_used_token (
                id INT AUTO_INCREMENT NOT NULL,
                device_id INT NOT NULL,
                jti VARCHAR(190) NOT NULL,
                used_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_C585928FC53CF2EA (jti),
                INDEX IDX_C585928F94A4C7D4 (device_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE print_gate_used_token
            ADD CONSTRAINT FK_C585928F94A4C7D4
            FOREIGN KEY (device_id) REFERENCES print_gate_device (id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->addSql('ALTER TABLE print_gate_used_token DROP FOREIGN KEY FK_C585928F94A4C7D4');
        }

        $this->addSql('DROP TABLE print_gate_used_token');
    }
}
