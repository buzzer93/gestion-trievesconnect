<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;
use App\Repository\CustomerRepository;

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
    /**
     * Grille tarifaire en centimes, identique à celle du débit manuel
     * (cf. modale crédit dans templates/admin/customer/index.html.twig)
     * pour rester cohérente avec le tarif déjà pratiqué au comptoir.
     * Ignore volontairement `pageCount`, comme le fait déjà ce système
     * manuel : le tarif porte sur le nombre de copies, pas sur le nombre
     * de pages par copie.
     */
    private const PRICES_CENTS = [
        'MONOCHROME' => ['A4' => 30, 'A3' => 60],
        'COLOR' => ['A4' => 50, 'A3' => 100],
    ];

    public function __construct(
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    public function evaluate(PrintAuthorizationRequest $request): PolicyDecision
    {
        $customer = $this->customerRepository->findOneByPhoneNumber($request->identifier);

        if (null === $customer) {
            return PolicyDecision::refused('Identifiant inconnu');
        }

        $costCents = $this->computeCostCents($request);

        if ($customer->getBalanceCents() < $costCents) {
            return PolicyDecision::refused('Crédits insuffisants');
        }

        $this->customerRepository->debitBalance($customer, $costCents);

        return PolicyDecision::authorized();
    }

    private function computeCostCents(PrintAuthorizationRequest $request): int
    {
        $colorMode = $request->printJob->colorMode ?? 'MONOCHROME';
        $paperSize = 'A3' === strtoupper((string) $request->printJob->paperSize) ? 'A3' : 'A4';

        return self::PRICES_CENTS[$colorMode][$paperSize] * $request->printJob->copies;
    }
}
