<?php

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Service;
use App\Form\LabelingType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $dataList = $session->get('dataList', []);

        // Migration des anciennes données de session (entités brutes → tableau {entity, format})
        if (!empty($dataList) && !is_array($dataList[0])) {
            $dataList = array_map(fn($entity) => ['entity' => $entity, 'format' => 'small'], $dataList);
            $session->set('dataList', $dataList);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $barCode = $data['barCode'];

            $data = $em->getRepository(Product::class)->findOneBy(['barCode' => $barCode]);

            // Si aucun produit n'est trouvé, recherche dans les services
            if (!$data) {
                $data = $em->getRepository(Service::class)->findOneBy(['barCode' => $barCode]);
            }
            if (!$data) {
                $data = $em->getRepository(Customer::class)->findOneBy(['id' => $barCode]);
            }

            if ($data) {
                $dataList[] = ['entity' => $data, 'format' => 'small'];
                $session->set('dataList', $dataList);
                $this->addFlash('success', "Étiquette ajoutée : {$data->getName()}");
            } else {
                $this->addFlash('danger', 'Étiquette introuvable.');
            }

            return $this->redirectToRoute('admin.labeling.index');
        }

        return $this->render('admin/labeling/index.html.twig', [
            'form' => $form,
            'dataList' => $dataList,
        ]);
    }

    #[Route('/delete/{index}/{all}', name: '.delete', methods: ['DELETE'])]
    public function delete(int $index, ?string $all, SessionInterface $session): Response
    {
        $dataList = $session->get('dataList', []);

        if ($all === 'true') {
            $session->remove('dataList');
            $this->addFlash('success', 'Toutes les étiquettes ont été supprimées.');
            return $this->redirectToRoute('admin.labeling.index');
        }

        if (isset($dataList[$index])) {
            unset($dataList[$index]);
            $dataList = array_values($dataList);
            $session->set('dataList', $dataList);
            $this->addFlash('success', 'Étiquette supprimée.');
        }

        return $this->redirectToRoute('admin.labeling.index');
    }

    #[Route('/toggle-format/{index}', name: '.toggleFormat', methods: ['POST'])]
    public function toggleFormat(int $index, SessionInterface $session): JsonResponse
    {
        $dataList = $session->get('dataList', []);

        if (isset($dataList[$index])) {
            $dataList[$index]['format'] = $dataList[$index]['format'] === 'large' ? 'small' : 'large';
            $session->set('dataList', $dataList);

            return new JsonResponse(['format' => $dataList[$index]['format']]);
        }

        return new JsonResponse(['error' => 'Index introuvable'], 404);
    }

    #[Route('/print', name: '.print')]
    public function printLabels(SessionInterface $session): Response
    {
        $dataList = $session->get('dataList', []);

        if (empty($dataList)) {
            $this->addFlash('danger', 'Aucun produit sélectionné.');
            return $this->redirectToRoute('admin.labeling.index');
        }

        return $this->render('admin/labeling/print.html.twig', [
            'dataList' => $dataList,
        ]);
    }
}
