<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: rendre address, postal_code, city nullables (SQLite rebuild table customer)
 */
final class Version20251007113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer: address/postal_code/city deviennent NULLABLE';
    }

    public function up(Schema $schema): void
    {
        // SQLite: recréer la table pour ajuster la nullabilité
        $this->addSql('CREATE TEMPORARY TABLE __temp__customer AS SELECT id, name, phone_number, address, postal_code, city, email, credits FROM customer');
        $this->addSql('DROP TABLE customer');
        $this->addSql("CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, credits INTEGER NOT NULL)");
        $this->addSql('INSERT INTO customer (id, name, phone_number, address, postal_code, city, email, credits) SELECT id, name, phone_number, address, postal_code, city, email, credits FROM __temp__customer');
        $this->addSql('DROP TABLE __temp__customer');
    }

    public function down(Schema $schema): void
    {
        // Revenir aux colonnes NOT NULL (valeurs NULL remplacées par chaine vide)
        $this->addSql("UPDATE customer SET address = COALESCE(address, ''), postal_code = COALESCE(postal_code, ''), city = COALESCE(city, '')");
        $this->addSql('CREATE TEMPORARY TABLE __temp__customer AS SELECT id, name, phone_number, address, postal_code, city, email, credits FROM customer');
        $this->addSql('DROP TABLE customer');
        $this->addSql("CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, postal_code VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, credits INTEGER NOT NULL)");
        $this->addSql('INSERT INTO customer (id, name, phone_number, address, postal_code, city, email, credits) SELECT id, name, phone_number, address, postal_code, city, email, credits FROM __temp__customer');
        $this->addSql('__temp__customer');
    }
}
