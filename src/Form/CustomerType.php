<?php

namespace App\Form;

use App\Entity\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
                'attr' => ['placeholder' => 'Nom du client']
            ])
            ->add('phoneNumber', TextType::class, [
                'label' => 'Téléphone',
                'constraints' => [new NotBlank(message: 'Le numéro de téléphone est obligatoire.'), new Length(min: 6, minMessage: 'Téléphone trop court')],
                'attr' => ['placeholder' => '+33...']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'constraints' => [new Email(message: 'Email invalide')],
                'attr' => ['placeholder' => 'client@example.com']
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['placeholder' => 'Rue ...']
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code Postal',
                'required' => false,
                'attr' => ['placeholder' => '75000']
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['placeholder' => 'Paris']
            ])
            ->add('credits', IntegerType::class, [
                'label' => 'Crédits d\'Impression',
                'attr' => ['min' => 0]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}
