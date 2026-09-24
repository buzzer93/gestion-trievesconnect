<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend configurables depuis l'admin deux valeurs auparavant codées en dur :
 * - print_price_rate : grille tarifaire d'impression (remplace
 *   PrintPolicyEvaluator::PRICES_CENTS), seedée avec les 4 valeurs
 *   actuelles (MONOCHROME/A4 30c, MONOCHROME/A3 60c, COLOR/A4 50c,
 *   COLOR/A3 100c) ;
 * - municipal_budget_settings : forfait mairie annuel (remplace
 *   Association::ANNUAL_MUNICIPAL_ALLOWANCE_CENTS), seedé avec 500 pages
 *   à 10 centimes (soit 50 €).
 */
final class Version20260808130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute print_price_rate et municipal_budget_settings (tarifs et forfait mairie configurables)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('CREATE TABLE print_price_rate (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, color_mode VARCHAR(32) NOT NULL, paper_size VARCHAR(32) NOT NULL, price_cents INTEGER NOT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_PRICE_RATE_TYPE ON print_price_rate (color_mode, paper_size)');

            $this->addSql('CREATE TABLE municipal_budget_settings (id INTEGER PRIMARY KEY NOT NULL, annual_page_allowance INTEGER NOT NULL, price_per_page_cents INTEGER NOT NULL)');
        } else {
            $this->addSql(<<<'SQL'
                CREATE TABLE print_price_rate (
                    id INT AUTO_INCREMENT NOT NULL,
                    color_mode VARCHAR(32) NOT NULL,
                    paper_size VARCHAR(32) NOT NULL,
                    price_cents INT NOT NULL,
                    UNIQUE INDEX UNIQ_PRICE_RATE_TYPE (color_mode, paper_size),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);

            $this->addSql(<<<'SQL'
                CREATE TABLE municipal_budget_settings (
                    id INT NOT NULL,
                    annual_page_allowance INT NOT NULL,
                    price_per_page_cents INT NOT NULL,
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
        }

        $this->addSql("INSERT INTO print_price_rate (color_mode, paper_size, price_cents) VALUES ('MONOCHROME', 'A4', 30)");
        $this->addSql("INSERT INTO print_price_rate (color_mode, paper_size, price_cents) VALUES ('MONOCHROME', 'A3', 60)");
        $this->addSql("INSERT INTO print_price_rate (color_mode, paper_size, price_cents) VALUES ('COLOR', 'A4', 50)");
        $this->addSql("INSERT INTO print_price_rate (color_mode, paper_size, price_cents) VALUES ('COLOR', 'A3', 100)");

        $this->addSql('INSERT INTO municipal_budget_settings (id, annual_page_allowance, price_per_page_cents) VALUES (1, 500, 10)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE print_price_rate');
        $this->addSql('DROP TABLE municipal_budget_settings');
    }
}
