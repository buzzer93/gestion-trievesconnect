<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PrintGateDevice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire volontairement limité à la configuration du form (pas de
 * logique métier ni de persistance ici, cf. règles projet). La clé
 * publique propose deux modes de saisie (upload OU collage) : les deux
 * champs correspondants sont `mapped: false`, le contrôleur décide lequel
 * utiliser et appelle lui-même PrintGateDevice::setPublicKey() -- cf.
 * PrintGateDeviceController pour le détail (aucune génération de clé
 * assistée côté serveur : la clé privée ne doit jamais transiter par le
 * serveur, cf. résumé technique §10).
 */
final class PrintGateDeviceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('computerId', TextType::class, [
                'label' => 'Identifiant technique du poste',
                // En édition, l'identifiant ne doit plus changer : il est
                // lié aux JWT déjà émis par ce poste (claim "sub").
                'disabled' => $options['is_edit'],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim'),
                    new Assert\Length(max: 190),
                ],
            ])
            ->add('hostname', TextType::class, [
                'label' => 'Nom d\'hôte',
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('displayName', TextType::class, [
                'label' => 'Nom affiché (optionnel)',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Poste actif',
                'required' => false,
            ])
            ->add('publicKeyFile', FileType::class, [
                'label' => 'Clé publique (fichier .pub/.pem)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(maxSize: '4k'),
                ],
            ])
            ->add('publicKeyText', TextareaType::class, [
                'label' => 'ou clé publique collée (PEM ou OpenSSH)',
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PrintGateDevice::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
