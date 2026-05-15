<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de développement — uniquement accessible en environnement dev.
 * Permet de pré-remplir la session avec des données de démonstration
 * pour les fonctionnalités Étiquetage et Inventaire.
 */
#[Route('/dev', name: 'dev.')]
class DevSessionController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Remplit la session avec les produits/services/clients de fixture
     * pour l'Étiquetage (clé "dataList").
     */
    #[Route('/seed-labeling', name: 'seed_labeling')]
    public function seedLabeling(SessionInterface $session): Response
    {
        $products  = $this->em->getRepository(Product::class)->findAll();
        $services  = $this->em->getRepository(Service::class)->findAll();
        $customers = $this->em->getRepository(Customer::class)->findAll();

        $dataList = [];

        foreach ($products as $product) {
            $dataList[] = ['entity' => $product, 'format' => 'small'];
        }
        foreach ($services as $service) {
            $dataList[] = ['entity' => $service, 'format' => 'small'];
        }
        foreach ($customers as $customer) {
            $dataList[] = ['entity' => $customer, 'format' => 'small'];
        }

        $session->set('dataList', $dataList);

        $this->addFlash('success', sprintf(
            'Session étiquetage remplie : %d produit(s), %d service(s), %d client(s).',
            count($products),
            count($services),
            count($customers)
        ));

        return $this->redirectToRoute('admin.labeling.index');
    }

    /**
     * Remplit la session avec les produits de fixture pour l'Inventaire
     * (clé "inventoryList").
     */
    #[Route('/seed-inventory', name: 'seed_inventory')]
    public function seedInventory(SessionInterface $session): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();

        $session->set('inventoryList', $products);

        $this->addFlash('success', sprintf(
            'Session inventaire remplie : %d produit(s).',
            count($products)
        ));

        return $this->redirectToRoute('admin.inventory.index');
    }

    /**
     * Vide les deux clés de session liées aux fixtures dev.
     */
    #[Route('/clear-session', name: 'clear_session')]
    public function clearSession(SessionInterface $session): Response
    {
        $session->remove('dataList');
        $session->remove('inventoryList');

        $this->addFlash('success', 'Session étiquetage et inventaire vidées.');

        return $this->redirectToRoute('admin.labeling.index');
    }
}
