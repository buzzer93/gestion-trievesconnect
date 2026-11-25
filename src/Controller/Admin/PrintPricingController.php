<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PrintPriceRate;
use App\Repository\PrintPriceRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page de réglages des 3 grilles tarifaires (CLIENT, ASSOCIATION,
 * MUNICIPAL -- cf. PrintPriceRate::SCOPE_*, décision du 2026-08-25). Pas
 * de FormType : édition en place des prix, plus ajout/suppression d'une
 * combinaison couleur/format via deux petites actions dédiées (cf. règles
 * projet anti-surengineering).
 *
 * La grille MUNICIPAL porte aussi la case "activée" par combinaison :
 * c'est elle qui définit l'éligibilité au financement mairie (cf.
 * PrintCostCalculator), pas une règle codée en dur.
 *
 * Supprimer une ligne n'affecte pas l'historique (PrintTransactionLine
 * fige son prix unitaire, aucune clé étrangère vers PrintPriceRate) ; en
 * revanche une combinaison sans tarif CLIENT/ASSOCIATION est refusée à
 * l'impression (cf. PrintPolicyEvaluator, REASON_RATE_NOT_CONFIGURED).
 */
#[Route('/admin/print-pricing', name: 'admin.print_pricing')]
#[IsGranted('ROLE_ADMIN')]
class PrintPricingController extends AbstractController
{
    private const PAPER_SIZE_PATTERN = '/^[A-Z0-9_-]{1,32}$/';

    #[Route('/', name: '.index', methods: ['GET', 'POST'])]
    public function index(Request $request, PrintPriceRateRepository $repository, EntityManagerInterface $em): Response
    {
        $rates = $repository->findAll();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('print_pricing_edit', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide');
            }

            foreach ($rates as $rate) {
                $priceCents = $this->parsePriceCents((string) $request->request->get('price_'.$rate->getId()));
                if (null !== $priceCents) {
                    $rate->setPriceCents($priceCents);
                }

                if (PrintPriceRate::SCOPE_MUNICIPAL === $rate->getScope()) {
                    $rate->setEnabled(null !== $request->request->get('enabled_'.$rate->getId()));
                }
            }

            $em->flush();
            $this->addFlash('success', 'Les tarifs d\'impression ont bien été mis à jour');

            return $this->redirectToRoute('admin.print_pricing.index');
        }

        return $this->render('admin/print_pricing/index.html.twig', [
            'clientRates' => $repository->findAllByScope(PrintPriceRate::SCOPE_CLIENT),
            'associationRates' => $repository->findAllByScope(PrintPriceRate::SCOPE_ASSOCIATION),
            'municipalRates' => $repository->findAllByScope(PrintPriceRate::SCOPE_MUNICIPAL),
            'scopes' => PrintPriceRate::SCOPES,
            'colorModes' => PrintPriceRate::COLOR_MODES,
        ]);
    }

    #[Route('/new', name: '.create', methods: ['POST'])]
    public function create(Request $request, PrintPriceRateRepository $repository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('print_price_rate_create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }

        $scope = (string) $request->request->get('scope');
        $colorMode = (string) $request->request->get('color_mode');
        // PrintGate met déjà le format en majuscules (cf. PrintAuthorizationManager) :
        // "a4" saisi ici doit correspondre à "A4" reçu de l'imprimante.
        $paperSize = strtoupper(trim((string) $request->request->get('paper_size')));
        $priceCents = $this->parsePriceCents((string) $request->request->get('price'));

        $error = match (true) {
            !in_array($scope, PrintPriceRate::SCOPES, true) => 'Grille tarifaire inconnue.',
            !in_array($colorMode, PrintPriceRate::COLOR_MODES, true) => 'Mode couleur inconnu.',
            1 !== preg_match(self::PAPER_SIZE_PATTERN, $paperSize) => 'Format invalide (ex. A4, A3, A5).',
            null === $priceCents => 'Prix invalide : saisissez un montant positif ou nul.',
            null !== $repository->findOneBy(['scope' => $scope, 'colorMode' => $colorMode, 'paperSize' => $paperSize]) => 'Ce tarif existe déjà dans cette grille : modifiez son prix directement dans le tableau.',
            default => null,
        };

        if (null !== $error) {
            $this->addFlash('danger', $error);

            return $this->redirectToRoute('admin.print_pricing.index');
        }

        $em->persist(new PrintPriceRate($scope, $colorMode, $paperSize, $priceCents));
        $em->flush();
        $this->addFlash('success', 'Le tarif a bien été ajouté');

        return $this->redirectToRoute('admin.print_pricing.index');
    }

    #[Route('/{id}/delete', name: '.delete', methods: ['DELETE'], requirements: ['id' => Requirement::DIGITS])]
    public function delete(PrintPriceRate $rate, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('print_price_rate_delete_'.$rate->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }

        $em->remove($rate);
        $em->flush();
        $this->addFlash('success', 'Le tarif a bien été supprimé');

        return $this->redirectToRoute('admin.print_pricing.index');
    }

    /**
     * Accepte "0.15" comme "0,15" ; null si la saisie est vide, non
     * numérique ou négative (le champ est alors ignoré / refusé).
     */
    private function parsePriceCents(string $priceEuros): ?int
    {
        $normalized = str_replace(',', '.', trim($priceEuros));
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            return null;
        }

        return (int) round((float) $normalized * 100);
    }
}
