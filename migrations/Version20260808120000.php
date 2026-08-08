<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le support des associations (forfait mairie PrintGate) :
 * - customer.type (colonne discriminante Single Table Inheritance
 *   Customer/Association, cf. App\Entity\Customer) ;
 * - customer.municipal_credits / customer.municipal_credits_renewed_at
 *   (crédit mairie, uniquement peuplés pour les associations) ;
 * - la table print_municipal_consumption, justificatif détaillé par
 *   impression payée (en tout ou partie) par le crédit mairie, seule
 *   source utilisée pour la facturation trimestrielle.
 */
final class Version20260808120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Association (STI sur customer) et print_municipal_consumption (forfait mairie)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TEMPORARY TABLE __temp__customer AS SELECT id, name, phone_number, address, postal_code, city, email, credits FROM customer');
            $this->addSql('DROP TABLE customer');
            $this->addSql("CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, credits INTEGER NOT NULL, type VARCHAR(255) NOT NULL DEFAULT 'customer', municipal_credits INTEGER NOT NULL DEFAULT 0, municipal_credits_renewed_at DATETIME DEFAULT NULL)");
            $this->addSql("INSERT INTO customer (id, name, phone_number, address, postal_code, city, email, credits, type, municipal_credits, municipal_credits_renewed_at) SELECT id, name, phone_number, address, postal_code, city, email, credits, 'customer', 0, NULL FROM __temp__customer");
            $this->addSql('DROP TABLE __temp__customer');

            $this->addSql('CREATE TABLE print_municipal_consumption (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, association_id INTEGER NOT NULL, print_job_id INTEGER NOT NULL, created_at DATETIME NOT NULL, page_count INTEGER NOT NULL, copies INTEGER NOT NULL, color_mode VARCHAR(32) DEFAULT NULL, paper_size VARCHAR(32) DEFAULT NULL, amount_spent_cents INTEGER NOT NULL, CONSTRAINT FK_PMC_ASSOCIATION FOREIGN KEY (association_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_PMC_ASSOCIATION ON print_municipal_consumption (association_id)');

            return;
        }

        $this->addSql("ALTER TABLE customer ADD type VARCHAR(255) NOT NULL DEFAULT 'customer', ADD municipal_credits INT NOT NULL DEFAULT 0, ADD municipal_credits_renewed_at DATETIME DEFAULT NULL");

        $this->addSql(<<<'SQL'
            CREATE TABLE print_municipal_consumption (
                id INT AUTO_INCREMENT NOT NULL,
                association_id INT NOT NULL,
                print_job_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                page_count INT NOT NULL,
                copies INT NOT NULL,
                color_mode VARCHAR(32) DEFAULT NULL,
                paper_size VARCHAR(32) DEFAULT NULL,
                amount_spent_cents INT NOT NULL,
                INDEX IDX_PMC_ASSOCIATION (association_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE print_municipal_consumption
            ADD CONSTRAINT FK_PMC_ASSOCIATION
            FOREIGN KEY (association_id) REFERENCES customer (id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('DROP TABLE print_municipal_consumption');

            $this->addSql('CREATE TEMPORARY TABLE __temp__customer AS SELECT id, name, phone_number, address, postal_code, city, email, credits FROM customer');
            $this->addSql('DROP TABLE customer');
            $this->addSql('CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, credits INTEGER NOT NULL)');
            $this->addSql('INSERT INTO customer (id, name, phone_number, address, postal_code, city, email, credits) SELECT id, name, phone_number, address, postal_code, city, email, credits FROM __temp__customer');
            $this->addSql('DROP TABLE __temp__customer');

            return;
        }

        $this->addSql('ALTER TABLE print_municipal_consumption DROP FOREIGN KEY FK_PMC_ASSOCIATION');
        $this->addSql('DROP TABLE print_municipal_consumption');
        $this->addSql('ALTER TABLE customer DROP type, DROP municipal_credits, DROP municipal_credits_renewed_at');
    }
}
