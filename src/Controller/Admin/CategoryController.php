<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/category",name: "admin.category")]
#[IsGranted('ROLE_ADMIN')]


class CategoryController extends AbstractController
{

    #[Route('/', name: '.index')]
    public function index(CategoryRepository $cr): Response
    {
        $categories = $cr->findAll();
        return $this->render(view: 'admin/category/index.html.twig',parameters: [
            'categories'=> $categories,
    ]);
    }

    #[Route(path:'/create',name: '.create')]
    public function create(Request $request, EntityManagerInterface $em)
    {
        $category = new category;
        $form = $this->createForm(CategoryType::class,$category);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $em->persist($category);
            $em->flush();
            $this->addFlash('success','La categorie a bien été ajoutée');
            return $this->redirectToRoute('admin.category.index');
        }
        return $this->render(view: 'admin/category/create.html.twig', parameters: [
            'form'=> $form
         ]);
    }

    #[Route(path:'/{id}/edit',name: '.edit', methods:['GET','POST'], requirements: ['id' => Requirement::DIGITS])]
    public function edit(category $category,Request $request,EntityManagerInterface $em)
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $em->flush();
            $this->addFlash('success','La categorie a bien été modifiée');
            return $this->redirectToRoute('admin.category.index');
        }
        return $this->render(view: 'admin/category/edit.html.twig', parameters: [
            'category'=> $category,
            'form'=> $form
         ]);
    }

    #[Route(path:'/{id}/delete',name: '.delete', methods: ['DELETE'] , requirements: ['id' => Requirement::DIGITS])]
    public function delete(category $category, EntityManagerInterface $em)
    {
        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'le Produit a bien été supprimée');
        return $this->redirectToRoute('admin.category.index');
    }
}
