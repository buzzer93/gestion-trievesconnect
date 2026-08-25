<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Association;
use App\Entity\PrintMunicipalConsumption;
use App\Entity\PrintPriceRate;
use App\Entity\PrintTransaction;
use App\Entity\User;
use App\Form\AssociationType;
use App\Repository\AssociationRepository;
use App\Repository\PrintMunicipalConsumptionRepository;
use App\Repository\PrintPriceRateRepository;
use App\Repository\PrintTransactionLineRepository;
use App\Service\PrintGate\PrintChargeContext;
use App\Service\PrintGate\PrintPolicyEvaluator;
use App\Service\PrintGate\PrintRefusalMessageFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/association', name: 'admin.association')]
#[IsGranted('ROLE_ADMIN')]
class AssociationController extends AbstractController
{
    #[Route('/', name: '.index')]
    public function index(AssociationRepository $repository, PrintPriceRateRepository $rateRepository): Response
    {
        return $this->render('admin/association/index.html.twig', [
            'associations' => $repository->findAll(),
            'rates' => $rateRepository->findAllByScope(PrintPriceRate::SCOPE_ASSOCIATION),
        ]);
    }

    #[Route('/create', name: '.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $association = new Association();
        $form = $this->createForm(AssociationType::class, $association);
        $form->get('balanceEuros')->setData('0.00');
        $form->get('municipalBalanceEuros')->setData('0.00');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBalancesFromForm($form, $association);
            $em->persist($association);
            $em->flush();
            $this->addFlash('success', 'L\'association a bien été créée');

            return $this->redirectToRoute('admin.association.index');
        }

        return $this->render('admin/association/create.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Fiche association : soldes courants + historique complet des
     * impressions, mairie et personnel séparés. Fusionne l'ancien journal
     * (PrintMunicipalConsumption, figé) et le nouveau (PrintTransactionLine,
     * seul alimenté désormais) -- cf. PHPDoc de Version20260825170000.
     * Contrairement à /consumption, pas de filtre trimestre ici -- c'est la
     * vue globale, la page trimestrielle reste dédiée à la facturation
     * mairie.
     */
    #[Route('/{id}', name: '.show', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function show(
        Association $association,
        PrintMunicipalConsumptionRepository $legacyRepository,
        PrintTransactionLineRepository $lineRepository,
        PrintPriceRateRepository $rateRepository,
    ): Response {
        $rows = [];

        foreach ($legacyRepository->findAllForAssociation($association) as $entry) {
            $rows[] = [
                'createdAt' => $entry->getCreatedAt(),
                'jobId' => $entry->getPrintJobId(),
                'colorMode' => $entry->getColorMode(),
                'paperSize' => $entry->getPaperSize(),
                'amountCents' => $entry->getAmountSpentCents(),
                'municipal' => $entry->isMunicipal(),
            ];
        }

        foreach ($lineRepository->findAllForCustomer($association) as $line) {
            $transaction = $line->getTransaction();
            $rows[] = [
                'createdAt' => $transaction->getCreatedAt(),
                'jobId' => $transaction->getJobId(),
                'colorMode' => $transaction->getColorMode(),
                'paperSize' => $transaction->getPaperSize(),
                'amountCents' => $line->getAmountCents(),
                'municipal' => PrintTransaction::FUNDING_MUNICIPAL === $line->getFundingSource(),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['createdAt'] <=> $a['createdAt']);

        return $this->render('admin/association/show.html.twig', [
            'association' => $association,
            'municipalEntries' => array_values(array_filter($rows, static fn (array $row): bool => $row['municipal'])),
            'personalEntries' => array_values(array_filter($rows, static fn (array $row): bool => !$row['municipal'])),
            'rates' => $rateRepository->findAllByScope(PrintPriceRate::SCOPE_ASSOCIATION),
        ]);
    }

    #[Route('/{id}/edit', name: '.edit', methods: ['GET', 'POST'], requirements: ['id' => Requirement::DIGITS])]
    public function edit(Association $association, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AssociationType::class, $association);
        $form->get('balanceEuros')->setData(number_format($association->getBalanceCents() / 100, 2, '.', ''));
        $form->get('municipalBalanceEuros')->setData(number_format($association->getMunicipalBalanceCents() / 100, 2, '.', ''));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBalancesFromForm($form, $association);
            $em->flush();
            $this->addFlash('success', 'L\'association a bien été modifiée');

            return $this->redirectToRoute('admin.association.show', ['id' => $association->getId()]);
        }

        return $this->render('admin/association/edit.html.twig', [
            'form' => $form,
            'association' => $association,
        ]);
    }

    #[Route('/{id}/delete', name: '.delete', methods: ['DELETE'], requirements: ['id' => Requirement::DIGITS])]
    public function delete(Association $association, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('association_delete_'.$association->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }

        $em->remove($association);
        $em->flush();
        $this->addFlash('success', 'L\'association a bien été supprimée');

        return $this->redirectToRoute('admin.association.index');
    }

    /**
     * Détail trimestriel pour la facturation mairie : total consommé par
     * type d'impression (couleur/format) et montant total. Fusionne
     * l'ancien journal (trimestres déjà facturés avant le 2026-08-25) et le
     * nouveau (cf. show()).
     */
    #[Route('/{id}/consumption', name: '.consumption', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function consumption(
        Association $association,
        Request $request,
        PrintMunicipalConsumptionRepository $legacyRepository,
        PrintTransactionLineRepository $lineRepository,
    ): Response {
        $now = new \DateTimeImmutable();
        $year = (int) $request->query->get('year', $now->format('Y'));
        $quarter = max(1, min(4, (int) $request->query->get('quarter', (string) (intdiv(((int) $now->format('n')) - 1, 3) + 1))));

        $byType = [];
        $totalCents = 0;
        $entries = [];

        foreach ($legacyRepository->findForQuarter($association, $year, $quarter) as $entry) {
            $key = ($entry->getColorMode() ?? 'MONOCHROME').' / '.($entry->getPaperSize() ?? 'A4');
            $byType[$key] ??= ['pageCount' => 0, 'copies' => 0, 'amountCents' => 0];
            $byType[$key]['pageCount'] += $entry->getPageCount();
            $byType[$key]['copies'] += $entry->getCopies();
            $byType[$key]['amountCents'] += $entry->getAmountSpentCents();
            $totalCents += $entry->getAmountSpentCents();

            $entries[] = [
                'createdAt' => $entry->getCreatedAt(),
                'jobId' => $entry->getPrintJobId(),
                'colorMode' => $entry->getColorMode(),
                'paperSize' => $entry->getPaperSize(),
                'copies' => $entry->getCopies(),
                'pageCount' => $entry->getPageCount(),
                'amountCents' => $entry->getAmountSpentCents(),
            ];
        }

        foreach ($lineRepository->findMunicipalForQuarter($association, $year, $quarter) as $line) {
            $transaction = $line->getTransaction();
            $key = ($transaction->getColorMode() ?? 'MONOCHROME').' / '.($transaction->getPaperSize() ?? 'A4');
            $byType[$key] ??= ['pageCount' => 0, 'copies' => 0, 'amountCents' => 0];
            $byType[$key]['pageCount'] += $transaction->getPageCount();
            $byType[$key]['copies'] += $line->getCopies();
            $byType[$key]['amountCents'] += $line->getAmountCents();
            $totalCents += $line->getAmountCents();

            $entries[] = [
                'createdAt' => $transaction->getCreatedAt(),
                'jobId' => $transaction->getJobId(),
                'colorMode' => $transaction->getColorMode(),
                'paperSize' => $transaction->getPaperSize(),
                'copies' => $line->getCopies(),
                'pageCount' => $transaction->getPageCount(),
                'amountCents' => $line->getAmountCents(),
            ];
        }

        usort($entries, static fn (array $a, array $b): int => $b['createdAt'] <=> $a['createdAt']);

        return $this->render('admin/association/consumption.html.twig', [
            'association' => $association,
            'year' => $year,
            'quarter' => $quarter,
            'byType' => $byType,
            'totalCents' => $totalCents,
            'entries' => $entries,
        ]);
    }

    /**
     * Ajustement manuel du solde personnel depuis la modale back-office
     * (comptoir) -- même contrat que CustomerController::credits(). Ne
     * touche jamais au crédit mairie : celui-ci n'évolue que par débit
     * d'impression (cf. printCharge ci-dessous) ou renouvellement annuel
     * (cf. RenewMunicipalCreditsCommand).
     */
    #[Route('/{id}/credits', name: '.credits', methods: ['POST'], requirements: ['id' => Requirement::DIGITS])]
    public function credits(Association $association, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $mode = $payload['mode'] ?? null; // 'add' ou 'remove'
        $cents = (int) ($payload['cents'] ?? 0);
        if ($cents <= 0 || !in_array($mode, ['add', 'remove'], true)) {
            return new JsonResponse(['error' => 'Requête invalide'], 400);
        }

        if ('add' === $mode) {
            $association->addBalanceCents($cents);
        } else {
            $association->removeBalanceCents($cents);
        }
        $em->flush();

        return new JsonResponse(['success' => true, 'credits' => $association->getBalanceCents()]);
    }

    /**
     * Ajustement manuel du crédit mairie depuis la modale back-office --
     * même contrat que credits() mais sur le solde mairie. Cas d'usage :
     * correction ponctuelle (ex: rallonge accordée par la mairie en cours
     * d'année), en dehors du renouvellement annuel automatique (cf.
     * RenewMunicipalCreditsCommand) et du débit par impression.
     */
    #[Route('/{id}/municipal-credits', name: '.municipal_credits', methods: ['POST'], requirements: ['id' => Requirement::DIGITS])]
    public function municipalCredits(Association $association, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $mode = $payload['mode'] ?? null; // 'add' ou 'remove'
        $cents = (int) ($payload['cents'] ?? 0);
        if ($cents <= 0 || !in_array($mode, ['add', 'remove'], true)) {
            return new JsonResponse(['error' => 'Requête invalide'], 400);
        }

        if ('add' === $mode) {
            $association->addMunicipalBalanceCents($cents);
        } else {
            $association->removeMunicipalBalanceCents($cents);
        }
        $em->flush();

        return new JsonResponse(['success' => true, 'municipalCredits' => $association->getMunicipalBalanceCents()]);
    }

    /**
     * Débit pour impression -- action unique, plus de choix entre solde
     * personnel et solde mairie (décision du 2026-08-25) : l'admin ne
     * fournit que les caractéristiques techniques, PrintPolicyEvaluator
     * détermine seul la ou les sources à débiter (mairie en priorité si
     * éligible, personnel sinon ou en complément). Même service que le
     * flux PrintGate automatique et que CustomerController::printCharge().
     */
    #[Route('/{id}/print-charge', name: '.print_charge', methods: ['POST'], requirements: ['id' => Requirement::DIGITS])]
    public function printCharge(Association $association, Request $request, PrintPolicyEvaluator $policyEvaluator): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $colorMode = strtoupper((string) ($payload['colorMode'] ?? ''));
        $paperSize = strtoupper((string) ($payload['paperSize'] ?? ''));
        $copies = max(1, (int) ($payload['copies'] ?? 1));

        $user = $this->getUser();

        $decision = $policyEvaluator->evaluate(new PrintChargeContext(
            beneficiary: $association,
            colorMode: $colorMode,
            paperSize: $paperSize,
            copies: $copies,
            createdBy: $user instanceof User ? $user : null,
            motif: 'Débit manuel (admin)',
        ));

        if (!$decision->authorized) {
            return new JsonResponse(['error' => PrintRefusalMessageFormatter::format($decision)], 400);
        }

        return new JsonResponse([
            'success' => true,
            'personalCredits' => $association->getBalanceCents(),
            'municipalCredits' => $association->getMunicipalBalanceCents(),
            'fundingSource' => $decision->fundingSource,
        ]);
    }

    private function applyBalancesFromForm(\Symfony\Component\Form\FormInterface $form, Association $association): void
    {
        $balanceEuros = $form->get('balanceEuros')->getData();
        if ($balanceEuros !== null && $balanceEuros !== '') {
            $association->setBalanceCents((int) round(floatval(str_replace(',', '.', (string) $balanceEuros)) * 100));
        }

        $municipalBalanceEuros = $form->get('municipalBalanceEuros')->getData();
        if ($municipalBalanceEuros !== null && $municipalBalanceEuros !== '') {
            $association->setMunicipalBalanceCents((int) round(floatval(str_replace(',', '.', (string) $municipalBalanceEuros)) * 100));
        }
    }
}
