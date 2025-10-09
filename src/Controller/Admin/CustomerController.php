<?php

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/customer', name: 'admin.customer')]
#[IsGranted('ROLE_ADMIN')]
class CustomerController extends AbstractController
{
    #[Route('/', name: '.index')]
    public function index(CustomerRepository $cr): Response
    {
        $customers = $cr->findAll();
        return $this->render('admin/customer/index.html.twig', [
            'customers' => $customers
        ]);
    }

    #[Route('/create', name: '.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $customer = new Customer();
    $form = $this->createForm(CustomerType::class, $customer);
    // Pré-remplir le champ non mappé balanceEuros
    $form->get('balanceEuros')->setData(number_format($customer->getBalanceCents() / 100, 2, '.', ''));
    $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion du solde saisi en euros (champ non mappé balanceEuros)
            $balanceEuros = $form->get('balanceEuros')->getData();
            if ($balanceEuros !== null && $balanceEuros !== '') {
                // Conversion en centimes
                $cents = (int)round(floatval(str_replace(',', '.', $balanceEuros)) * 100);
                $customer->setBalanceCents($cents);
            }
            $em->persist($customer);
            $em->flush();
            $this->addFlash('success', 'Le client a bien été créé');
            return $this->redirectToRoute('admin.customer.index');
        }

        return $this->render('admin/customer/create.html.twig', [
            'form' => $form
        ]);
    }

    #[Route('/{id}/edit', name: '.edit', methods: ['GET','POST'], requirements: ['id' => Requirement::DIGITS])]
    public function edit(Customer $customer, Request $request, EntityManagerInterface $em): Response
    {
    $form = $this->createForm(CustomerType::class, $customer);
    // Pré-remplir le champ non mappé balanceEuros
    $form->get('balanceEuros')->setData(number_format($customer->getBalanceCents() / 100, 2, '.', ''));
    $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Champ non mappé balanceEuros -> mettre à jour le solde en centimes
            $balanceEuros = $form->get('balanceEuros')->getData();
            if ($balanceEuros !== null && $balanceEuros !== '') {
                $cents = (int)round(floatval(str_replace(',', '.', $balanceEuros)) * 100);
                $customer->setBalanceCents($cents);
            }
            $em->flush();
            $this->addFlash('success', 'Le client a bien été modifié');
            return $this->redirectToRoute('admin.customer.index');
        }

        return $this->render('admin/customer/edit.html.twig', [
            'form' => $form,
            'customer' => $customer
        ]);
    }

    #[Route('/{id}/delete', name: '.delete', methods: ['DELETE'], requirements: ['id' => Requirement::DIGITS])]
    public function delete(Customer $customer, EntityManagerInterface $em): Response
    {
        $em->remove($customer);
        $em->flush();
        $this->addFlash('success', 'Le client a bien été supprimé');
        return $this->redirectToRoute('admin.customer.index');
    }

    // Impression étiquette client (réutilise template labeling)
    #[Route('/{id}/print', name: '.print', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function print(Customer $customer): Response
    {
        return $this->render('admin/labeling/print.html.twig', [
            'dataList' => [$customer],
            'data' => $customer,
        ]);
    }

    #[Route('/{id}/credits', name: '.credits', methods: ['POST'], requirements: ['id' => Requirement::DIGITS])]
    public function credits(Customer $customer, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $mode = $payload['mode'] ?? null; // 'add' ou 'remove'
        $cents = (int)($payload['cents'] ?? 0);
        if ($cents <= 0 || !in_array($mode, ['add','remove'], true)) {
            return new JsonResponse(['error' => 'Requête invalide'], 400);
        }
        if ($mode === 'add') {
            $customer->addBalanceCents($cents);
        } else {
            $customer->removeBalanceCents($cents);
        }
        $em->flush();
        return new JsonResponse(['success' => true, 'credits' => $customer->getBalanceCents()]);
    }

    #[Route('/{id}/card-print', name: '.card_print', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function cardPrint(Customer $customer): Response
    {
        return $this->render('admin/customer/cardPrint.html.twig', [
            'customer' => $customer
        ]);
    }

    #[Route('/{id}/print-charge', name: '.print_charge', methods: ['POST'], requirements: ['id' => Requirement::DIGITS])]
    public function printCharge(Customer $customer, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $cents = (int)($payload['cents'] ?? 0);
        if ($cents <= 0) {
            return new JsonResponse(['error' => 'Montant invalide'], 400);
        }
        if ($customer->getBalanceCents() < $cents) {
            return new JsonResponse(['error' => 'Solde insuffisant'], 400);
        }
        $customer->removeBalanceCents($cents);
        $em->flush();
        return new JsonResponse(['success' => true, 'credits' => $customer->getBalanceCents()]);
    }
}
