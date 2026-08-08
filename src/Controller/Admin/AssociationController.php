<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Association;
use App\Entity\PrintMunicipalConsumption;
use App\Form\AssociationType;
use App\Repository\AssociationRepository;
use App\Repository\PrintMunicipalConsumptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function index(AssociationRepository $repository): Response
    {
        return $this->render('admin/association/index.html.twig', [
            'associations' => $repository->findAll(),
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
    public function show(Association $association, PrintMunicipalConsumptionRepository $repository): Response
    {
        $entries = $repository->findAllForAssociation($association);

        return $this->render('admin/association/show.html.twig', [
            'association' => $association,
            'municipalEntries' => array_values(array_filter($entries, static fn (PrintMunicipalConsumption $entry): bool => $entry->isMunicipal())),
            'personalEntries' => array_values(array_filter($entries, static fn (PrintMunicipalConsumption $entry): bool => !$entry->isMunicipal())),
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
