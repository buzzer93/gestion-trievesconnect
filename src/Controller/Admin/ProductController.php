<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\ServiceRepository;
use App\Service\DataExport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/product",name: "admin.product")]
#[IsGranted('ROLE_ADMIN')]


class ProductController extends AbstractController
{

    private DataExport $dataExport;

    public function __construct(DataExport $dataExport)
    {
        $this->dataExport = $dataExport;
    }


    #[Route('/', name: '.index')]
    public function index(ProductRepository $pr): Response
    {
        $products = $pr->findAll();
        foreach($products as $product){
            $product->updateMargin();
        }
        return $this->render(view: 'admin/product/index.html.twig',parameters: ['products'=> $products]);
    }

    #[Route('/export', name: '.export')]
    public function export(ProductRepository $pr, ServiceRepository $sr): Response
    {
        $products = $pr->findAll();
        $services = $sr->findAll();

        $this->dataExport->exportData($products, $services);

         // Chemin du fichier à télécharger
         $filePath = './export/ProductsDatas.xlsx';

         // Vérifier si le fichier existe
         if (!file_exists($filePath)) {
             throw $this->createNotFoundException("Le fichier n'a pas été trouvé.");
         }
     
        $this->addFlash('success','Téléchargement du fichier d\'export en cours');
        return $this->file($filePath);
    }
    

    #[Route(path:'/create',name: '.create')]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em)
    {
        
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();
                $this->addFlash('success','Le produit a bien été ajoutée');
                return $this->redirectToRoute('admin.product.index');
                }
                
            
        return $this->render(view: 'admin/product/create.html.twig', parameters: [
            'form'=> $form
         ]);
    }

    #[Route(path:'/{id}/edit',name: '.edit', methods:['GET','POST'], requirements: ['id' => Requirement::DIGITS])]
    public function edit(product $product,Request $request,EntityManagerInterface $em)
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $em->flush();
            $this->addFlash('success','Le Produit a bien été modifiée');
            return $this->redirectToRoute('admin.product.index');
        }
        return $this->render(view: 'admin/product/edit.html.twig', parameters: [
            'product'=> $product,
            'form'=> $form
         ]);
    }

    #[Route(path:'/{id}/delete',name: '.delete', methods: ['DELETE'] , requirements: ['id' => Requirement::DIGITS])]
    public function delete(Product $product, EntityManagerInterface $em)
    {
        $em->remove($product);
        $em->flush();
        $this->addFlash('success', 'le Produit a bien été supprimée');
        return $this->redirectToRoute('admin.product.index');
    }
}
