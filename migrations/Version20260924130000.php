<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Téléphone client facultatif : depuis Version20260910193000, le compte
 * est identifié par customer.reference (carte, PrintGate), le téléphone
 * n'est plus qu'une information de contact.
 *
 * SQLite (tests) : modifier la nullabilité d'une colonne impose de recréer
 * toute la table customer (héritage STI, nombreuses colonnes) -- même choix
 * que Version20260910193000, la contrainte n'est appliquée qu'en MySQL /
 * MariaDB (production).
 */
final class Version20260924130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend customer.phone_number facultatif';
    }

    public function up(Schema $schema): void
    {
        if ('sqlite' === $this->connection->getDatabasePlatform()->getName()) {
            return;
        }

        $this->addSql('ALTER TABLE customer MODIFY phone_number VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if ('sqlite' === $this->connection->getDatabasePlatform()->getName()) {
            return;
        }

        $this->addSql("UPDATE customer SET phone_number = '' WHERE phone_number IS NULL");
        $this->addSql('ALTER TABLE customer MODIFY phone_number VARCHAR(255) NOT NULL');
    }
}
