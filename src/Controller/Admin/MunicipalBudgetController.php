<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\MunicipalBudgetSettingsRepository;
use App\Repository\PrintTransactionLineRepository;
use App\Service\MunicipalBudgetSummaryProvider;
use App\Service\MunicipalCreditsRenewalService;
use App\Service\MunicipalInvoicePdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
        MunicipalBudgetSummaryProvider $summaryProvider,
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

        [$year, $quarter] = $this->resolveYearAndQuarter($request);
        [$prevYear, $prevQuarter] = PrintTransactionLineRepository::previousQuarter($year, $quarter);
        [$nextYear, $nextQuarter] = PrintTransactionLineRepository::nextQuarter($year, $quarter);

        $summary = $summaryProvider->forQuarter($year, $quarter);

        return $this->render('admin/municipal_budget/index.html.twig', [
            'settings' => $settings,
            'year' => $year,
            'quarter' => $quarter,
            'prevYear' => $prevYear,
            'prevQuarter' => $prevQuarter,
            'nextYear' => $nextYear,
            'nextQuarter' => $nextQuarter,
            'byAssociation' => $summary['byAssociation'],
            'totalCents' => $summary['totalCents'],
        ]);
    }

    /**
     * Facture PDF trimestrielle à envoyer à la mairie -- même récap que
     * la page ci-dessus (par association + total), sur le même
     * trimestre sélectionné (query params year/quarter).
     */
    #[Route('/export-pdf', name: '.export_pdf', methods: ['GET'])]
    public function exportPdf(
        Request $request,
        MunicipalBudgetSummaryProvider $summaryProvider,
        MunicipalInvoicePdfGenerator $pdfGenerator,
    ): Response {
        [$year, $quarter] = $this->resolveYearAndQuarter($request);

        $summary = $summaryProvider->forQuarter($year, $quarter);
        $pdf = $pdfGenerator->generate($summary['byAssociation'], $summary['totalCents'], $year, $quarter);

        $response = new Response($pdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('facture-mairie-%d-T%d.pdf', $year, $quarter))
        );
        $response->headers->set('Cache-Control', 'no-store, no-cache');

        return $response;
    }

    /**
     * @return array{0: int, 1: int} [year, quarter]
     */
    private function resolveYearAndQuarter(Request $request): array
    {
        $now = new \DateTimeImmutable();
        $year = (int) $request->query->get('year', (string) PrintTransactionLineRepository::currentSchoolYearStart($now));
        $quarter = max(1, min(4, (int) $request->query->get('quarter', (string) PrintTransactionLineRepository::currentQuarter($now))));

        return [$year, $quarter];
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
