<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute customer.reference : identifiant unique de code-barres carte et
 * de recherche PrintGate, remplace le téléphone pour ces deux usages (cf.
 * Customer::$reference, CustomerReferenceGenerator, décision du
 * 2026-09-10). Motif : deux comptes peuvent légitimement partager le même
 * téléphone (même personne gérant plusieurs associations), ce qui faisait
 * planter PrintAuthorizationManager (NonUniqueResultException) -- cf.
 * paires "Le Souffle du Qi"/"AcCorDer" et "Gallet Anne"/"Les Dés Calés"
 * trouvées en production le 2026-09-10.
 *
 * Backfill en PHP (pas en SQL pur) : chaque compte existant reçoit un code
 * généré selon le même format que CustomerReferenceGenerator (préfixe 7777
 * + 5 chiffres aléatoires + chiffre de contrôle Luhn), dupliqué ici
 * volontairement -- une migration ne doit pas dépendre d'un service
 * applicatif qui peut changer après coup (cf. convention Doctrine).
 */
final class Version20260910193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute customer.reference (identifiant carte/PrintGate, remplace le téléphone), backfill des comptes existants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD reference VARCHAR(10) DEFAULT NULL');

        $usedReferences = [];
        $rows = $this->connection->fetchAllAssociative('SELECT id FROM customer');
        foreach ($rows as $row) {
            do {
                $reference = $this->generateReference();
            } while (isset($usedReferences[$reference]));
            $usedReferences[$reference] = true;

            $this->addSql('UPDATE customer SET reference = ? WHERE id = ?', [$reference, $row['id']]);
        }

        $this->addSql('CREATE UNIQUE INDEX UNIQ_CUSTOMER_REFERENCE ON customer (reference)');

        // SQLite (tests) : ALTER COLUMN ... NOT NULL n'est pas supporté sans
        // recréer la table. Le champ reste NOT NULL au niveau entité PHP
        // (jamais laissé vide par l'application) ; seule la contrainte
        // UNIQUE ci-dessus (déjà appliquée sur les deux plateformes) est
        // ce qui compte réellement pour empêcher la collision d'origine.
        if ('sqlite' !== $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('ALTER TABLE customer MODIFY reference VARCHAR(10) NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ('sqlite' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('DROP INDEX UNIQ_CUSTOMER_REFERENCE');
            $this->addSql('ALTER TABLE customer DROP COLUMN reference');

            return;
        }

        $this->addSql('DROP INDEX UNIQ_CUSTOMER_REFERENCE ON customer');
        $this->addSql('ALTER TABLE customer DROP reference');
    }

    /**
     * Même format que CustomerReferenceGenerator::generateCandidate() --
     * volontairement dupliqué, cf. PHPDoc de classe.
     */
    private function generateReference(): string
    {
        $random = '';
        for ($i = 0; $i < 5; $i++) {
            $random .= random_int(0, 9);
        }
        $payload = '7777' . $random;

        return $payload . $this->luhnCheckDigit($payload);
    }

    private function luhnCheckDigit(string $payload): int
    {
        $sum = 0;
        $alternate = true;
        for ($i = strlen($payload) - 1; $i >= 0; $i--) {
            $digit = (int) $payload[$i];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = !$alternate;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
