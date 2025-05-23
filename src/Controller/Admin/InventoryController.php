<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\InventoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/inventory", name: "admin.inventory")]
#[IsGranted('ROLE_ADMIN')]
class InventoryController extends AbstractController
{
    #[Route('/index', name: '.index')]
    public function index(Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $form = $this->createForm(InventoryType::class);
        $form->handleRequest($request);
        $inventoryList = $session->get('inventoryList', []);
        $inventoryValue = 0;
        foreach ($inventoryList as $product) {
            $inventoryValue += $product->getStock() * $product->getPurchasePrice();
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $barCode = $data['barCode'];

            $product = $em->getRepository(Product::class)->findOneBy(['barCode' => $barCode]);
            if ($product) {

                // Vérifier si le produit est déjà dans la liste
                if (!in_array($product, $inventoryList, false)) {
                    $product->setStock($data['quantity']);
                    $inventoryList[] = $product;
                    $session->set('inventoryList', $inventoryList);
                    $this->addFlash('success', "Produit ajoutée à l'inventaire: {$product->getName()}");
                    return $this->redirectToRoute('admin.inventory.index', [
                        'form' => $form,
                        'inventoryList' => $inventoryList,
                    ]);
                } else {
                    $this->addFlash('info', 'Produit déjà présente dans la liste à été mis a jour');
                    return $this->redirectToRoute('admin.inventory.index', [
                        'form' => $form,
                        'inventoryList' => $inventoryList,
                    ]);
                }
            } else {
                $this->addFlash('danger', 'Produit introuvable.');
                return $this->redirectToRoute('admin.inventory.index', [
                    'form' => $form,
                    'inventoryList' => $inventoryList,
                ]);
            }
        }
        return $this->render('admin/inventory/index.html.twig', [

            'form' => $form,
            'inventoryList' => $inventoryList,
            'inventoryValue' => $inventoryValue
        ]);
    }

    #[Route('/delete/{id}/{all}', name: '.delete', methods: ['DELETE'])]
    public function delete(int $id, ?string $all, SessionInterface $session): Response
    {
        // Récupérer la liste des produits depuis la session
        $inventoryList = $session->get('inventoryList', []);

        if ($all === 'true') {
            // Si "all" est passé et égal à "true", supprimer toute la liste de la session
            $session->remove('inventoryList');
            $this->addFlash('success', 'Tous les produits ont été supprimées de l\'inventaire.');
            return $this->redirectToRoute('admin.inventory.index');
        }

        // Rechercher l'index du produit dans la liste par ID
        foreach ($inventoryList as $index => $data) {
            if ($data->getId() === $id) {
                // Supprimer une seule occurrence
                unset($inventoryList[$index]);
                // Réindexer le tableau après suppression
                $inventoryList = array_values($inventoryList);
                $session->set('inventoryList', $inventoryList);
                $this->addFlash('success', 'Produit supprimée de l\'inventaire.');
                
            }
        }

        // Rediriger vers l'index après suppression
        return $this->redirectToRoute('admin.inventory.index');
    }

    #[Route('/print', name: '.print')]
    public function printLabels(SessionInterface $session,): Response
    {
        // Récupérer la liste des produits depuis la session
        $inventoryList = $session->get('inventoryList', []);

        if (empty($inventoryList)) {
            $this->addFlash('danger', 'Aucun produit sélectionné.');
            return $this->redirectToRoute('admin.inventory.index');
        }

        return $this->render('admin/labeling/print.html.twig', [
            'inventoryList' => $inventoryList
        ]);
    }
}
