<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('barCode', TextType::class, [
            'label' => 'Code bar'
        ])
            ->add('name', TextType::class, [
                'label' => 'Nom'
            ])
            ->add('purchasePrice', MoneyType::class, [
                'label' => 'Prix d\'achat HT',
                'currency' => 'EUR',
                'data'=> 0
            ])->add('marginRate', MoneyType::class, [
                'attr' => ['readonly' => false],
                'required'=>false,
                'mapped' => false,  // Ce champ ne sera pas pris en compte lors de la soumission du formulaire
                'label' => 'marge'
            ])
            ->add('sellingPrice', MoneyType::class, [
                'label' => 'Prix de vente TTC',

                'currency' => 'EUR'
            ])
            ->add('description', TextareaType::class, [
                'required'=>false,
                'label' => 'Description',
                'empty_data' => ''
            ])
            ->add('slug', type: HiddenType::class)
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer'
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, $this->autoSlug(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
    public function autoSlug(PreSubmitEvent $e): void
    {
        $data = $e->getData();
        if (empty($data['slug'])) {
            $slugger = new AsciiSlugger();
            $data['slug'] = strtolower($slugger->slug($data['name']));
            $e->setData($data);
        }
    }
}
