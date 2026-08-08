<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Association;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Mêmes champs que CustomerType (une association reste un client, cf.
 * Association extends Customer) plus le crédit mairie, saisi en euros
 * comme balanceEuros sur CustomerType pour la même raison (le solde est
 * stocké en centimes, cf. Customer::$credits).
 */
class AssociationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'association',
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
                'attr' => ['placeholder' => 'Nom de l\'association'],
            ])
            ->add('phoneNumber', TextType::class, [
                'label' => 'Téléphone',
                'constraints' => [new NotBlank(message: 'Le numéro de téléphone est obligatoire.'), new Length(min: 6, minMessage: 'Téléphone trop court')],
                'attr' => ['placeholder' => '+33...'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'constraints' => [new Email(message: 'Email invalide')],
                'attr' => ['placeholder' => 'contact@association.fr'],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['placeholder' => 'Rue ...'],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code Postal',
                'required' => false,
                'attr' => ['placeholder' => '75000'],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['placeholder' => 'Paris'],
            ])
            ->add('balanceEuros', NumberType::class, [
                'mapped' => false,
                'label' => 'Solde personnel (€)',
                'help' => 'Crédit propre de l\'association, débité une fois le crédit mairie épuisé.',
                'scale' => 2,
                'attr' => ['step' => '0.01', 'min' => 0],
            ])
            ->add('municipalBalanceEuros', NumberType::class, [
                'mapped' => false,
                'label' => 'Solde mairie (€)',
                'help' => 'Forfait mairie restant. Renouvelé automatiquement chaque 1er janvier (montant réglable dans "Budget mairie").',
                'scale' => 2,
                'attr' => ['step' => '0.01', 'min' => 0],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Association::class,
        ]);
    }
}
