<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend le téléphone facultatif et synchronise la référence client';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'sqlite') {
            $this->addSql('CREATE TEMPORARY TABLE __temp__customer AS SELECT id, name, phone_number, address, postal_code, city, email, credits, reference FROM customer');
            $this->addSql('DROP TABLE customer');
            $this->addSql('CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, reference VARCHAR(32) DEFAULT NULL, credits INTEGER NOT NULL)');
            $this->addSql('INSERT INTO customer (id, name, phone_number, address, postal_code, city, email, reference, credits) SELECT id, name, phone_number, address, postal_code, city, email, reference, credits FROM __temp__customer');
            $this->addSql('DROP TABLE __temp__customer');
        } else {
            $this->addSql('DROP INDEX UNIQ_CUSTOMER_REFERENCE ON customer');
            $this->addSql('ALTER TABLE customer MODIFY phone_number VARCHAR(255) DEFAULT NULL');
        }

        $customers = $this->connection->fetchAllAssociative('SELECT id, phone_number FROM customer ORDER BY id ASC');
        $references = [];
        foreach ($customers as $customer) {
            $baseReference = $customer['phone_number'] ?: (string) (7777000000 + (int) $customer['id']);
            $reference = $baseReference;
            $suffix = 0;
            while (isset($references[$reference])) {
                $suffix++;
                $reference = $baseReference . '-' . $suffix;
            }
            $references[$reference] = true;
            $this->connection->update('customer', ['reference' => $reference], ['id' => $customer['id']]);
        }

        $this->addSql('CREATE UNIQUE INDEX UNIQ_CUSTOMER_REFERENCE ON customer (reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE customer SET phone_number = '' WHERE phone_number IS NULL");
        $this->addSql('DROP INDEX UNIQ_CUSTOMER_REFERENCE');
        $this->addSql('ALTER TABLE customer MODIFY phone_number VARCHAR(255) NOT NULL');
    }
}