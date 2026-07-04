<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PrintGateDevice;
use App\Form\PrintGateDeviceType;
use App\Repository\PrintGateDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/printgate-device', name: 'admin.printgate_device')]
#[IsGranted('ROLE_ADMIN')]
class PrintGateDeviceController extends AbstractController
{
    #[Route('/', name: '.index')]
    public function index(Request $request, PrintGateDeviceRepository $deviceRepository): Response
    {
        return $this->render('admin/printgate_device/index.html.twig', [
            'devices' => $deviceRepository->search($request->query->get('q')),
            'query' => $request->query->get('q', ''),
        ]);
    }

    #[Route(path: '/create', name: '.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        // Instance "vide" : le constructeur exige computerId/hostname mais
        // les vrais champs sont remplis par le formulaire (mapping standard
        // vers setComputerId()/setHostname()), pas par le constructeur ici.
        $device = new PrintGateDevice('', '');

        $form = $this->createForm(PrintGateDeviceType::class, $device, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->applyPublicKeyFromForm($form, $device)) {
                return $this->render('admin/printgate_device/create.html.twig', ['form' => $form]);
            }

            $em->persist($device);
            $em->flush();

            $this->addFlash('success', \sprintf('Poste "%s" créé.', $device->getHostname()));

            return $this->redirectToRoute('admin.printgate_device.index');
        }

        return $this->render('admin/printgate_device/create.html.twig', ['form' => $form]);
    }

    #[Route(path: '/{id}/edit', name: '.edit', methods: ['GET', 'POST'], requirements: ['id' => Requirement::DIGITS])]
    public function edit(Request $request, PrintGateDevice $device, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PrintGateDeviceType::class, $device, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->applyPublicKeyFromForm($form, $device)) {
                return $this->render('admin/printgate_device/edit.html.twig', ['form' => $form, 'device' => $device]);
            }

            $em->flush();

            $this->addFlash('success', \sprintf('Poste "%s" mis à jour.', $device->getHostname()));

            return $this->redirectToRoute('admin.printgate_device.index');
        }

        return $this->render('admin/printgate_device/edit.html.twig', ['form' => $form, 'device' => $device]);
    }

    #[Route(path: '/{id}/toggle', name: '.toggle', methods: ['POST'], requirements: ['id' => Requirement::DIGITS])]
    public function toggle(Request $request, PrintGateDevice $device, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('printgate_device_toggle_'.$device->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }

        $device->setEnabled(!$device->isEnabled());
        $em->flush();

        $this->addFlash('success', \sprintf(
            'Poste "%s" %s.',
            $device->getHostname(),
            $device->isEnabled() ? 'activé' : 'désactivé',
        ));

        return $this->redirectToRoute('admin.printgate_device.index');
    }

    /**
     * Applique la clé publique soumise (upload prioritaire sur le texte
     * collé), en conservant la clé existante si aucune des deux n'est
     * fournie (cas d'une édition qui ne touche pas la clé). Retourne
     * false et ajoute une erreur de formulaire si le format semble invalide.
     */
    private function applyPublicKeyFromForm(FormInterface $form, PrintGateDevice $device): bool
    {
        $uploadedFile = $form->get('publicKeyFile')->getData();
        $pastedKey = trim((string) $form->get('publicKeyText')->getData());

        $publicKey = match (true) {
            $uploadedFile instanceof UploadedFile => trim((string) file_get_contents($uploadedFile->getPathname())),
            '' !== $pastedKey => $pastedKey,
            default => $device->getPublicKey(),
        };

        if (null !== $publicKey && '' !== $publicKey && !$this->looksLikeAPublicKey($publicKey)) {
            $form->get('publicKeyText')->addError(new FormError(
                'Format de clé publique non reconnu (attendu : PEM "-----BEGIN PUBLIC KEY-----" ou OpenSSH "ssh-ed25519 ...").',
            ));

            return false;
        }

        $device->setPublicKey('' === $publicKey ? null : $publicKey);

        return true;
    }

    private function looksLikeAPublicKey(string $key): bool
    {
        return str_starts_with($key, '-----BEGIN PUBLIC KEY-----')
            || str_starts_with($key, 'ssh-ed25519 ')
            || str_starts_with($key, 'ssh-rsa ');
    }
}
