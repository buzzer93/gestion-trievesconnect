<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\AssociationRepository;
use App\Repository\MunicipalBudgetSettingsRepository;
use App\Repository\PrintMunicipalConsumptionRepository;
use App\Repository\PrintTransactionLineRepository;
use App\Service\MunicipalCreditsRenewalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page de réglages du forfait mairie annuel (montant en euros crédité à
 * chaque association lors du renouvellement, cf.
 * RenewMunicipalCreditsCommand) et récap de la consommation du crédit
 * mairie par association, trimestre par trimestre. Réglage unique
 * (singleton, cf. MunicipalBudgetSettings) : pas de CRUD, un simple
 * formulaire d'édition en place.
 */
#[Route('/admin/municipal-budget', name: 'admin.municipal_budget')]
#[IsGranted('ROLE_ADMIN')]
class MunicipalBudgetController extends AbstractController
{
    #[Route('/', name: '.index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MunicipalBudgetSettingsRepository $settingsRepository,
        AssociationRepository $associationRepository,
        PrintMunicipalConsumptionRepository $consumptionRepository,
        PrintTransactionLineRepository $lineRepository,
        EntityManagerInterface $em,
    ): Response {
        $settings = $settingsRepository->getSettings();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('municipal_budget_edit', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide');
            }

            $annualAllowanceEuros = $request->request->get('annual_allowance_euros');
            if (null !== $annualAllowanceEuros && '' !== $annualAllowanceEuros) {
                $settings->setAnnualAllowanceCents((int) round(floatval(str_replace(',', '.', (string) $annualAllowanceEuros)) * 100));
            }

            $em->flush();
            $this->addFlash('success', 'Le budget mairie a bien été mis à jour');

            return $this->redirectToRoute('admin.municipal_budget.index');
        }

        $now = new \DateTimeImmutable();
        $year = (int) $request->query->get('year', $now->format('Y'));
        $quarter = max(1, min(3, (int) $request->query->get('quarter', (string) PrintTransactionLineRepository::currentQuarter($now))));
        [$prevYear, $prevQuarter] = PrintTransactionLineRepository::previousQuarter($year, $quarter);
        [$nextYear, $nextQuarter] = PrintTransactionLineRepository::nextQuarter($year, $quarter);

        // Une entrée par association, y compris celles sans consommation
        // ce trimestre (0 €), pour un récap complet plutôt qu'une liste
        // tronquée aux seules associations actives.
        $byAssociation = [];
        foreach ($associationRepository->findAll() as $association) {
            $byAssociation[$association->getId()] = [
                'association' => $association,
                'amountCents' => 0,
            ];
        }

        $totalCents = 0;

        // Fusionne l'ancien journal (trimestres déjà facturés avant le
        // 2026-08-25) et le nouveau (seul alimenté désormais) -- cf.
        // PHPDoc de Version20260825170000.
        foreach ($consumptionRepository->findMunicipalForQuarterAllAssociations($year, $quarter) as $entry) {
            $id = $entry->getAssociation()->getId();
            $byAssociation[$id]['amountCents'] += $entry->getAmountSpentCents();
            $totalCents += $entry->getAmountSpentCents();
        }

        foreach ($lineRepository->findMunicipalForQuarterAllAssociations($year, $quarter) as $line) {
            $id = $line->getTransaction()->getCustomer()->getId();
            if (isset($byAssociation[$id])) {
                $byAssociation[$id]['amountCents'] += $line->getAmountCents();
            }
            $totalCents += $line->getAmountCents();
        }

        return $this->render('admin/municipal_budget/index.html.twig', [
            'settings' => $settings,
            'year' => $year,
            'quarter' => $quarter,
            'prevYear' => $prevYear,
            'prevQuarter' => $prevQuarter,
            'nextYear' => $nextYear,
            'nextQuarter' => $nextQuarter,
            'byAssociation' => $byAssociation,
            'totalCents' => $totalCents,
        ]);
    }

    /**
     * Recharge manuellement le crédit mairie de toutes les associations au
     * montant configuré ci-dessus -- décision du 2026-08-25 : déclenchement
     * volontaire par l'admin, pas de cron automatique au 1er janvier (cf.
     * MunicipalCreditsRenewalService). Remet le solde au forfait, sans
     * reporter le reliquat non consommé (comportement inchangé).
     */
    #[Route('/renew', name: '.renew', methods: ['POST'])]
    public function renew(Request $request, MunicipalCreditsRenewalService $renewalService): Response
    {
        if (!$this->isCsrfTokenValid('municipal_budget_renew', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }

        $count = $renewalService->renewAll();

        $this->addFlash('success', sprintf('Crédit mairie rechargé pour %d association(s).', $count));

        return $this->redirectToRoute('admin.municipal_budget.index');
    }
}
