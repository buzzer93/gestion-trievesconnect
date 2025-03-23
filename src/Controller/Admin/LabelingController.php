<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\LabelingType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/labeling", name: "admin.labeling")]
#[IsGranted('ROLE_ADMIN')]
class LabelingController extends AbstractController
{
    #[Route('/index', name: '.index')]
    public function index(Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $form = $this->createForm(LabelingType::class);
        $form->handleRequest($request);
        $productList = $session->get('productList', []);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $barCode = $data['barCode'];
    
            // Recherche du produit en fonction du code-barre
            $product = $em->getRepository(Product::class)->findOneBy(['barCode' => $barCode]);
    
            if ($product) {    
                // Vérifier si le produit est déjà dans la liste
                if (!in_array($product, $productList, false)) {
                    $productList[] = $product;
                    $session->set('productList', $productList);
                    $this->addFlash('success', "Étiquette ajoutée : {$product->getName()}");
                    return $this->redirectToRoute('admin.labeling.index', [
                        'form' => $form,
                        'productList' => $productList,
                    ]);
                } else {
                    $this->addFlash('info', 'Étiquette déjà présente dans la liste.');
                    return $this->redirectToRoute('admin.labeling.index', [
                        'form' => $form,
                        'productList' => $productList,
                    ]);
                }
            } else {
                $this->addFlash('danger', 'Étiquette introuvable.');
                return $this->redirectToRoute('admin.labeling.index', [
                    'form' => $form,
                    'productList' => $productList,
                ]);
            }
        }
        return $this->render('admin/labeling/index.html.twig', [
            'form' => $form,
            'productList' => $productList,
        ]);
    }

    #[Route('/delete/{id}/{all}', name: '.delete', methods: ['DELETE'])]
public function delete(int $id, ?string $all, SessionInterface $session): Response
{
    // Récupérer la liste des produits depuis la session
    $productList = $session->get('productList', []);

    if ($all === 'true') {
        // Si "all" est passé et égal à "true", supprimer toute la liste de la session
        $session->remove('productList');
        $this->addFlash('success', 'Toutes les étiquettes ont été supprimées.');
        return $this->redirectToRoute('admin.labeling.index');
    }

    // Rechercher l'index du produit dans la liste par ID
    foreach ($productList as $index => $product) {
        if ($product->getId() === $id) {
            // Supprimer une seule occurrence
            unset($productList[$index]);
            // Réindexer le tableau après suppression
            $productList = array_values($productList);
            $session->set('productList', $productList);
            $this->addFlash('success', 'Étiquette supprimée.');
            break;
        }
    }

    // Rediriger vers l'index après suppression
    return $this->redirectToRoute('admin.labeling.index');
}

    #[Route('/print', name: '.print')]
    public function printLabels( SessionInterface $session,): Response
    {
        // Récupérer la liste des produits depuis la session
        $productList = $session->get('productList', []);

        if (empty($productList)) {
            $this->addFlash('danger', 'Aucun produit sélectionné.');
            return $this->redirectToRoute('admin.labeling.index');
        }

        return $this->render('admin/labeling/print.html.twig', [
            'productList' => $productList
        ]);
    }
}
