<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Association;
use App\Entity\PrintMunicipalConsumption;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de démonstration pour le forfait mairie PrintGate (Association +
 * historique d'impression). Dans son propre groupe et sa propre classe --
 * pas dans AppFixtures -- pour pouvoir être chargée seule avec --append
 * sans retomber sur la contrainte d'unicité de User::$username (AppFixtures
 * recrée l'utilisateur "demo" à chaque exécution, ce qui casse --append si
 * ce compte existe déjà) :
 *
 *     php bin/console doctrine:fixtures:load --append --group=association_demo
 */
class AssociationDemoFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['association_demo'];
    }

    public function load(ObjectManager $om): void
    {
        // 3 profils différents : crédit mairie partiellement consommé,
        // épuisé (bascule sur le perso), et quasi intact.
        $now = new \DateTimeImmutable();

        $boulistes = new Association();
        $boulistes->setName('Amicale des Boulistes de Trièves');
        $boulistes->setPhoneNumber('0700000001');
        $boulistes->setAddress('2 place du Marché');
        $boulistes->setPostalCode('38710');
        $boulistes->setCity('Mens');
        $boulistes->setEmail('contact@boulistes-trieves.fr');
        $boulistes->renewMunicipalCredits($now, 5000);
        $boulistes->setMunicipalBalanceCents(2200);
        $boulistes->setBalanceCents(1000);
        $om->persist($boulistes);

        $comiteFetes = new Association();
        $comiteFetes->setName('Comité des Fêtes de Mens');
        $comiteFetes->setPhoneNumber('0700000002');
        $comiteFetes->setAddress('Mairie de Mens');
        $comiteFetes->setPostalCode('38710');
        $comiteFetes->setCity('Mens');
        $comiteFetes->setEmail('comitefetes.mens@example.com');
        $comiteFetes->renewMunicipalCredits($now, 5000);
        $comiteFetes->setMunicipalBalanceCents(0);
        $comiteFetes->setBalanceCents(400);
        $om->persist($comiteFetes);

        $clubPhoto = new Association();
        $clubPhoto->setName('Club Photo Trièves');
        $clubPhoto->setPhoneNumber('0700000003');
        $clubPhoto->setAddress('5 rue des Écoles');
        $clubPhoto->setPostalCode('38650');
        $clubPhoto->setCity('Monestier-de-Clermont');
        $clubPhoto->setEmail('club.photo.trieves@example.com');
        $clubPhoto->renewMunicipalCredits($now, 5000);
        $clubPhoto->setMunicipalBalanceCents(3450);
        $clubPhoto->setBalanceCents(800);
        $om->persist($clubPhoto);

        // Historique d'impression (mairie + perso séparés), pour la fiche
        // association (show) et le récap "Budget mairie". Dates réelles
        // fixées par le constructeur de PrintMunicipalConsumption (toujours
        // "maintenant") -- toutes les lignes tombent donc dans le
        // trimestre en cours.
        $consumptionData = [
            [$boulistes, 900001, 20, 20, 'COLOR', 'A4', 1000, PrintMunicipalConsumption::SOURCE_MUNICIPAL],
            [$boulistes, 900002, 60, 60, 'MONOCHROME', 'A4', 1800, PrintMunicipalConsumption::SOURCE_MUNICIPAL],
            [$boulistes, 900003, 5, 5, 'COLOR', 'A3', 500, PrintMunicipalConsumption::SOURCE_PERSONAL],

            [$comiteFetes, 900101, 400, 400, 'MONOCHROME', 'A4', 4000, PrintMunicipalConsumption::SOURCE_MUNICIPAL],
            [$comiteFetes, 900102, 20, 20, 'COLOR', 'A4', 1000, PrintMunicipalConsumption::SOURCE_MUNICIPAL],
            [$comiteFetes, 900103, 10, 10, 'MONOCHROME', 'A4', 100, PrintMunicipalConsumption::SOURCE_PERSONAL],

            [$clubPhoto, 900201, 8, 8, 'COLOR', 'A3', 800, PrintMunicipalConsumption::SOURCE_MUNICIPAL],
            [$clubPhoto, 900202, 15, 15, 'COLOR', 'A4', 750, PrintMunicipalConsumption::SOURCE_MUNICIPAL],
        ];

        foreach ($consumptionData as [$association, $jobId, $pageCount, $copies, $colorMode, $paperSize, $amountCents, $source]) {
            $om->persist(new PrintMunicipalConsumption(
                association: $association,
                printJobId: $jobId,
                pageCount: $pageCount,
                copies: $copies,
                colorMode: $colorMode,
                paperSize: $paperSize,
                amountSpentCents: $amountCents,
                fundingSource: $source,
            ));
        }

        $om->flush();
    }
}
