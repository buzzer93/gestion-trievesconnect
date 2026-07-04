<?php

declare(strict_types=1);

namespace App\Service\PrintGate;

use App\DTO\PrintGate\PrintAuthorizationRequest;

/**
 * Cœur métier, pur et stateless : ne dépend d'aucune classe Doctrine ni
 * HTTP, testable en isolation totale.
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
 * Point d'extension V2 (résumé technique §16) : quotas, restrictions
 * couleur, limite de pages. Ajouter une méthode privée par règle
 * (signature `?string` : null si acceptée, raison de refus sinon) et
 * l'enregistrer dans $rules, évaluées en fail-fast (une seule reason
 * retournée). En V1, $rules est vide : aucune règle métier active au-delà
 * de ce qui est déjà garanti par les couches JWT et validation.
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
     * @var list<callable(PrintAuthorizationRequest): (string|null)>
     */
    private readonly array $rules;

    public function __construct()
    {
        $this->rules = [
            // Aucune règle V1. Exemple de forme attendue pour une future
            // règle V2 (laissé en commentaire plutôt qu'implémenté à vide,
            // pour ne pas suggérer une limite qui n'existe pas encore) :
            //
            // fn (PrintAuthorizationRequest $r): ?string => $r->printJob->pageCount > 500
            //     ? 'Nombre de pages supérieur à la limite autorisée'
            //     : null,
        ];
    }

    public function evaluate(PrintAuthorizationRequest $request): PolicyDecision
    {
        foreach ($this->rules as $rule) {
            $reason = $rule($request);

            if (null !== $reason) {
                return PolicyDecision::refused($reason);
            }
        }

        return PolicyDecision::authorized();
    }
}
