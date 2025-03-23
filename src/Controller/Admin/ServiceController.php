<?php

namespace App\Controller\Admin;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use App\Service\DataExport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/admin/service",name: "admin.service")]
#[IsGranted('ROLE_ADMIN')]


class ServiceController extends AbstractController
{

    #[Route('/', name: '.index')]
    public function index(ServiceRepository $sr): Response
    {
        $services = $sr->findAll();
        foreach($services as $service){
            $service->updateMargin();
        }
        return $this->render(view: 'admin/service/index.html.twig',parameters: ['services'=> $services]);
    }
    

    #[Route(path:'/create',name: '.create')]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em)
    {
        
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($service);
            $em->flush();
                $this->addFlash('success','Le Service a bien été ajoutée');
                return $this->redirectToRoute('admin.service.index');
                }
                
            
        return $this->render(view: 'admin/service/create.html.twig', parameters: [
            'form'=> $form
         ]);
    }

    #[Route(path:'/{id}/edit',name: '.edit', methods:['GET','POST'], requirements: ['id' => Requirement::DIGITS])]
    public function edit(Service $service,Request $request,EntityManagerInterface $em)
    {
        $form = $this->createForm(serviceType::class, $service);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $em->flush();
            $this->addFlash('success','Le Service a bien été modifiée');
            return $this->redirectToRoute('admin.service.index');
        }
        return $this->render(view: 'admin/service/edit.html.twig', parameters: [
            'service'=> $service,
            'form'=> $form
         ]);
    }

    #[Route(path:'/{id}/delete',name: '.delete', methods: ['DELETE'] , requirements: ['id' => Requirement::DIGITS])]
    public function delete(Service $service, EntityManagerInterface $em)
    {
        $em->remove($service);
        $em->flush();
        $this->addFlash('success', 'le Service a bien été supprimée');
        return $this->redirectToRoute('admin.service.index');
    }
}
