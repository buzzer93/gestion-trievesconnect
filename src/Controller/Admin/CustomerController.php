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
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion des crédits saisis
            $credits = $form->get('credits')->getData();
            if (is_numeric($credits) && $credits > 0) {
                $customer->addCredits((int)$credits);
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
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // On peut choisir d'ajouter les crédits saisis aux existants plutôt que de remplacer
            $credits = $form->get('credits')->getData();
            if (is_numeric($credits) && $credits !== null) {
                $customer->setCredits((int)$credits); // incrémente
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
        $quantity = (int)($payload['quantity'] ?? 0);
        if ($quantity <= 0 || !in_array($mode, ['add','remove'], true)) {
            return new JsonResponse(['error' => 'Requête invalide'], 400);
        }
        if ($mode === 'add') {
            $customer->addCredits($quantity);
        } else {
            $customer->removeCredits($quantity);
        }
        $em->flush();
        return new JsonResponse(['success' => true, 'credits' => $customer->getCredits()]);
    }

    #[Route('/{id}/card-print', name: '.card_print', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function cardPrint(Customer $customer): Response
    {
        return $this->render('admin/customer/cardPrint.html.twig', [
            'customer' => $customer
        ]);
    }
}
