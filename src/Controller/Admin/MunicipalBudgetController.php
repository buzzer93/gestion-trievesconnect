<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\MunicipalBudgetSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page de réglages du forfait mairie annuel (nombre de pages + prix par
 * page), qui détermine le montant crédité à chaque association lors du
 * renouvellement (cf. RenewMunicipalCreditsCommand). Réglage unique
 * (singleton, cf. MunicipalBudgetSettings) : pas de CRUD, un simple
 * formulaire d'édition en place.
 */
#[Route('/admin/municipal-budget', name: 'admin.municipal_budget')]
#[IsGranted('ROLE_ADMIN')]
class MunicipalBudgetController extends AbstractController
{
    #[Route('/', name: '.index', methods: ['GET', 'POST'])]
    public function index(Request $request, MunicipalBudgetSettingsRepository $repository, EntityManagerInterface $em): Response
    {
        $settings = $repository->getSettings();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('municipal_budget_edit', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide');
            }

            $annualPageAllowance = $request->request->get('annual_page_allowance');
            if (null !== $annualPageAllowance && '' !== $annualPageAllowance) {
                $settings->setAnnualPageAllowance((int) $annualPageAllowance);
            }

            $pricePerPageEuros = $request->request->get('price_per_page_euros');
            if (null !== $pricePerPageEuros && '' !== $pricePerPageEuros) {
                $settings->setPricePerPageCents((int) round(floatval(str_replace(',', '.', (string) $pricePerPageEuros)) * 100));
            }

            $em->flush();
            $this->addFlash('success', 'Le budget mairie a bien été mis à jour');

            return $this->redirectToRoute('admin.municipal_budget.index');
        }

        return $this->render('admin/municipal_budget/index.html.twig', [
            'settings' => $settings,
        ]);
    }
}
