<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une référence unique aux clients pour leur code-barres';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD reference VARCHAR(64) DEFAULT NULL');

        $customers = $this->connection->fetchFirstColumn('SELECT id FROM customer ORDER BY id ASC');
        foreach ($customers as $id) {
            $this->connection->update('customer', [
                'reference' => (string) (7777000000 + (int) $id),
            ], [
                'id' => $id,
            ]);
        }

        $this->addSql('CREATE UNIQUE INDEX UNIQ_CUSTOMER_REFERENCE ON customer (reference)');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CUSTOMER_REFERENCE');
        $this->addSql('ALTER TABLE customer DROP reference');
    }
}