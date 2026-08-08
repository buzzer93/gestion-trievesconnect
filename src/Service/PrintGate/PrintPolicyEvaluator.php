<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\Entity\Association;
use App\Repository\AssociationRepository;
use App\Repository\CustomerRepository;
use App\Repository\PrintPriceRateRepository;

/**
 * Cœur métier de l'autorisation PrintGate.
 *
 * DÉLIBÉRÉMENT SANS règle "poste connu" / "poste actif" ici, contrairement
 * à ce qu'un premier sous-plan envisageait. Ces vérifications sont déjà
 * appliquées en amont, avant même que ce service ne soit atteint :
 * - poste inconnu / désactivé -> PrintGateJwtVerifier + PrintGateAuthorizeIntegrityListener
 *   (kernel.request, étape 5), qui répondent respectivement 401/403 et
 *   empêchent le contrôleur d'être invoqué ;
 * - identifiant vide ou blanc -> Assert\NotBlank(normalizer: 'trim') sur
 *   PrintAuthorizationRequest::$identifier, qui répond 422 avant que le
 *   contrôleur ne construise le DTO.
 * Les réintroduire ici serait soit du code mort (le device est garanti
 * non nul et actif quand ce service s'exécute), soit une duplication de
 * la validation déjà faite sur le DTO -- à éviter (cf. règles projet
 * anti-surengineering : ne pas dupliquer une vérification déjà faite
 * ailleurs).
 *
 * N'est plus stateless depuis l'ajout de la règle "client + crédits" :
 * relier une impression à un Customer et débiter son solde nécessite un
 * accès à Doctrine (CustomerRepository). Toute règle purement dérivée de
 * la requête (quotas, limite de pages...) peut s'ajouter comme un
 * contrôle supplémentaire avant le débit, en retournant tôt un
 * PolicyDecision::refused() -- même logique fail-fast qu'avant.
 *
 * `identifier` est le numéro de téléphone du client (même identifiant que
 * pour la carte client physique/scannée, cf. Customer::$phoneNumber) --
 * pas un identifiant PrintGate dédié : aucun champ supplémentaire à
 * renseigner pour qu'un client existant puisse utiliser PrintGate.
 *
 * Non final (contrairement aux autres classes PrintGate) : PHPUnit ne peut
 * pas doubler une classe finale, et PrintAuthorizationManagerTest a besoin
 * de la mocker pour tester la traduction PolicyDecision -> réponse HTTP en
 * isolation. Pas d'interface pour autant : une seule implémentation ne
 * justifie pas cette abstraction (cf. règles projet anti-surengineering).
 */
class PrintPolicyEvaluator
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly AssociationRepository $associationRepository,
        private readonly PrintPriceRateRepository $priceRateRepository,
    ) {
    }

    public function evaluate(PrintAuthorizationRequest $request): PolicyDecision
    {
        $customer = $this->customerRepository->findOneByPhoneNumber($request->identifier);

        if (null === $customer) {
            return PolicyDecision::refused('Identifiant inconnu');
        }

        $costCents = $this->computeCostCents($request);

        if (null === $costCents) {
            return PolicyDecision::refused('Tarif non configuré');
        }

        // Une association dispose d'un solde mairie en plus de son solde
        // personnel : le crédit mairie est débité en priorité, le crédit
        // personnel ne couvre que ce que le crédit mairie ne peut pas
        // (cf. AssociationRepository::debitForPrintJob()).
        if ($customer instanceof Association) {
            $availableCents = $customer->getBalanceCents() + $customer->getMunicipalBalanceCents();

            if ($availableCents < $costCents) {
                return PolicyDecision::refused('Crédits insuffisants');
            }

            $this->associationRepository->debitForPrintJob($customer, $costCents, $request->printJob);

            return PolicyDecision::authorized();
        }

        if ($customer->getBalanceCents() < $costCents) {
            return PolicyDecision::refused('Crédits insuffisants');
        }

        $this->customerRepository->debitBalance($customer, $costCents);

        return PolicyDecision::authorized();
    }

    /**
     * Tarif d'impression, lu depuis PrintPriceRate (page admin "Tarifs
     * d'impression") plutôt qu'une grille codée en dur, pour rester
     * configurable sans déploiement. Ignore volontairement `pageCount` :
     * le tarif porte sur le nombre de copies, pas sur le nombre de pages
     * par copie -- même convention que l'ancien débit manuel (cf. modale
     * crédit dans templates/admin/customer/index.html.twig).
     *
     * Retourne null si aucun tarif n'est configuré pour la combinaison
     * demandée -- fail-closed, pour ne jamais autoriser une impression à
     * un tarif arbitraire ou nul.
     */
    private function computeCostCents(PrintAuthorizationRequest $request): ?int
    {
        $colorMode = $request->printJob->colorMode ?? 'MONOCHROME';
        $paperSize = 'A3' === strtoupper((string) $request->printJob->paperSize) ? 'A3' : 'A4';

        $rate = $this->priceRateRepository->findOneByTypeAndFormat($colorMode, $paperSize);

        if (null === $rate) {
            return null;
        }

        return $rate->getPriceCents() * $request->printJob->copies;
    }
}
