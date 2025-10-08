<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration Customer credits: ajoute colonne credits si manquante.
 */
final class Version20251007000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonne credits (INT) sur customer si absente';
    }

    public function up(Schema $schema): void
    {
        // SQLite: pas de commande conditionnelle simple, on tente un ajout si non existant.
        // Si la colonne existe déjà, cette migration devra être ajustée manuellement.
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'sqlite') {
            // On récupère le schéma actuel
            $columns = $this->connection->fetchFirstColumn("PRAGMA table_info('customer')");
            if (!in_array('credits', $columns, true)) {
                $this->addSql('ALTER TABLE customer ADD COLUMN credits INTEGER DEFAULT 0 NOT NULL');
            }
        } else {
            // Pour d'autres SGBD (MySQL/PostgreSQL) on tente un ajout direct
            $this->addSql('ALTER TABLE customer ADD credits INT DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Attention: suppression de la colonne credits
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'sqlite') {
            // SQLite ne supporte pas DROP COLUMN simplement (il faudrait recréer la table) -> on laisse vide pour éviter perte de données.
        } else {
            $this->addSql('ALTER TABLE customer DROP COLUMN credits');
        }
    }
}
