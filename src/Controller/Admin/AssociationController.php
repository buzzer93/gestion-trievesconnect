<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\DTO\PrintGate\PrintJobPayload;
use App\Entity\Association;
use App\Entity\PrintMunicipalConsumption;
use App\Form\AssociationType;
use App\Repository\AssociationRepository;
use App\Repository\PrintMunicipalConsumptionRepository;
use App\Repository\PrintPriceRateRepository;
use App\Service\PrintGate\PrintCostCalculator;
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
            'rates' => $rateRepository->findAll(),
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
     * impressions, mairie et personnel séparés (cf.
     * PrintMunicipalConsumption::$fundingSource). Contrairement à
     * /consumption, pas de filtre trimestre ici -- c'est la vue globale,
     * la page trimestrielle reste dédiée à la facturation mairie.
     */
    #[Route('/{id}', name: '.show', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function show(Association $association, PrintMunicipalConsumptionRepository $repository, PrintPriceRateRepository $rateRepository): Response
    {
        $entries = $repository->findAllForAssociation($association);

        return $this->render('admin/association/show.html.twig', [
            'association' => $association,
            'municipalEntries' => array_values(array_filter($entries, static fn (PrintMunicipalConsumption $entry): bool => $entry->isMunicipal())),
            'personalEntries' => array_values(array_filter($entries, static fn (PrintMunicipalConsumption $entry): bool => !$entry->isMunicipal())),
            'rates' => $rateRepository->findAll(),
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
     * type d'impression (couleur/format) et montant total, calculés à la
     * volée depuis PrintMunicipalConsumption (cf. son PHPDoc -- pas
     * d'agrégat stocké séparément).
     */
    #[Route('/{id}/consumption', name: '.consumption', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function consumption(Association $association, Request $request, PrintMunicipalConsumptionRepository $repository): Response
    {
        $now = new \DateTimeImmutable();
        $year = (int) $request->query->get('year', $now->format('Y'));
        $quarter = max(1, min(4, (int) $request->query->get('quarter', (string) (intdiv(((int) $now->format('n')) - 1, 3) + 1))));

        $entries = $repository->findForQuarter($association, $year, $quarter);

        $byType = [];
        $totalCents = 0;
        foreach ($entries as $entry) {
            $key = ($entry->getColorMode() ?? 'MONOCHROME').' / '.($entry->getPaperSize() ?? 'A4');
            $byType[$key] ??= ['pageCount' => 0, 'copies' => 0, 'amountCents' => 0];
            $byType[$key]['pageCount'] += $entry->getPageCount();
            $byType[$key]['copies'] += $entry->getCopies();
            $byType[$key]['amountCents'] += $entry->getAmountSpentCents();
            $totalCents += $entry->getAmountSpentCents();
        }

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
     * Débit manuel pour impression depuis la modale back-office. Réutilise
     * AssociationRepository::debitForPrintJob() -- pas de débit direct du
     * solde personnel ici -- pour respecter la priorité au crédit mairie et
     * alimenter l'historique (PrintMunicipalConsumption) exactement comme
     * le flux PrintGate automatique (cf. PrintPolicyEvaluator).
     */
    #[Route('/{id}/print-charge', name: '.print_charge', methods: ['POST'], requirements: ['id' => Requirement::DIGITS])]
    public function printCharge(Association $association, Request $request, AssociationRepository $repository, PrintCostCalculator $costCalculator): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $colorMode = strtoupper((string) ($payload['colorMode'] ?? ''));
        $paperSize = strtoupper((string) ($payload['paperSize'] ?? ''));
        $copies = max(1, (int) ($payload['copies'] ?? 1));

        $cents = $costCalculator->computeCostCents($colorMode, $paperSize, $copies);
        if (null === $cents) {
            return new JsonResponse(['error' => 'Tarif non configuré'], 400);
        }

        $availableCents = $association->getBalanceCents() + $association->getMunicipalBalanceCents();
        if ($availableCents < $cents) {
            return new JsonResponse(['error' => 'Solde insuffisant'], 400);
        }

        $printJob = new PrintJobPayload(
            jobId: random_int(1, PHP_INT_MAX),
            printerName: 'Back-office',
            documentName: 'Débit manuel (admin)',
            copies: $copies,
            paperSize: $paperSize,
            colorMode: $colorMode,
        );
        $repository->debitForPrintJob($association, $cents, $printJob);

        return new JsonResponse([
            'success' => true,
            'personalCredits' => $association->getBalanceCents(),
            'municipalCredits' => $association->getMunicipalBalanceCents(),
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
