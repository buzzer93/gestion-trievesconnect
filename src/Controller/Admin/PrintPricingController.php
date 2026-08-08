<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\PrintPriceRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page de réglages des tarifs d'impression (grille fixe couleur x format,
 * cf. PrintPriceRate) -- pas de FormType ni de CRUD ici : les 4
 * combinaisons sont fixes et connues à l'avance, donc un simple formulaire
 * d'édition en place suffit (cf. règles projet anti-surengineering).
 */
#[Route('/admin/print-pricing', name: 'admin.print_pricing')]
#[IsGranted('ROLE_ADMIN')]
class PrintPricingController extends AbstractController
{
    #[Route('/', name: '.index', methods: ['GET', 'POST'])]
    public function index(Request $request, PrintPriceRateRepository $repository, EntityManagerInterface $em): Response
    {
        $rates = $repository->findAll();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('print_pricing_edit', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide');
            }

            foreach ($rates as $rate) {
                $priceEuros = $request->request->get('price_'.$rate->getId());
                if (null !== $priceEuros && '' !== $priceEuros) {
                    $rate->setPriceCents((int) round(floatval(str_replace(',', '.', (string) $priceEuros)) * 100));
                }
            }

            $em->flush();
            $this->addFlash('success', 'Les tarifs d\'impression ont bien été mis à jour');

            return $this->redirectToRoute('admin.print_pricing.index');
        }

        return $this->render('admin/print_pricing/index.html.twig', [
            'rates' => $rates,
        ]);
    }
}
