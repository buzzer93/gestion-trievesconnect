<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire customer.print_gate_identifier : PrintGate utilise finalement
 * le numéro de téléphone du client (déjà utilisé pour la carte client)
 * plutôt qu'un identifiant dédié (cf. Version20260705215148).
 */
final class Version20260705220220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire customer.print_gate_identifier (PrintGate utilise phoneNumber)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TEMPORARY TABLE __temp__customer AS SELECT id, name, phone_number, address, postal_code, city, email, credits FROM customer');
            $this->addSql('DROP TABLE customer');
            $this->addSql('CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, credits INTEGER NOT NULL)');
            $this->addSql('INSERT INTO customer (id, name, phone_number, address, postal_code, city, email, credits) SELECT id, name, phone_number, address, postal_code, city, email, credits FROM __temp__customer');
            $this->addSql('DROP TABLE __temp__customer');

            return;
        }

        $this->addSql('ALTER TABLE customer DROP INDEX UNIQ_81398E09B50E272, DROP print_gate_identifier');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TEMPORARY TABLE __temp__customer AS SELECT id, name, phone_number, address, postal_code, city, email, credits FROM customer');
            $this->addSql('DROP TABLE customer');
            $this->addSql('CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, credits INTEGER NOT NULL, print_gate_identifier VARCHAR(190) DEFAULT NULL)');
            $this->addSql('INSERT INTO customer (id, name, phone_number, address, postal_code, city, email, credits) SELECT id, name, phone_number, address, postal_code, city, email, credits FROM __temp__customer');
            $this->addSql('DROP TABLE __temp__customer');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_81398E09B50E272 ON customer (print_gate_identifier)');

            return;
        }

        $this->addSql('ALTER TABLE customer ADD print_gate_identifier VARCHAR(190) DEFAULT NULL, ADD UNIQUE INDEX UNIQ_81398E09B50E272 (print_gate_identifier)');
    }
}
