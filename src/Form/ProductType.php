<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('barCode', TextType::class, [
            'label' => 'Code-barre'
        ])
            ->add('name', TextType::class, [
                'label' => 'Nom'
            ])
            ->add('supplier', TextType::class, [
                'label' => 'Fournisseur'
            ])
            ->add('purchasePrice', MoneyType::class, [
                'label' => 'Prix d\'achat HT',
                'currency' => 'EUR'
            ])
            ->add('marginRate', PercentType::class, [
                'attr' => ['readonly' => false],
                'required'=>false,
                'mapped' => false,  // Ce champ ne sera pas pris en compte lors de la soumission du formulaire
                'label' => 'Taux de marge'
            ])
            ->add('sellingPrice', MoneyType::class, [
                'label' => 'Prix de vente TTC',

                'currency' => 'EUR'
            ])
            ->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'multiple' => true,
                'by_reference' => false,
                'expanded' => true,
                'label_attr' => ['class' => 'checkbox-inline '], // class label
                'attr' => ['class' => 'checkbox-inline '] // class input
            ])
            ->add('comment', TextareaType::class, [
                'required'=>false,
                'label' => 'Commentaire',
                'empty_data' => ''
            ])
            ->add('slug', type: HiddenType::class)
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer'
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, $this->autoSlug(...))
            ->addEventListener(FormEvents::POST_SUBMIT, $this->attachTimestamps(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
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
    public function attachTimestamps(PostSubmitEvent $e): void
    {
        $data = $e->getData();
        if (!($data instanceof Product)) {
            return;
        }
        $data->setUpdatedAt(new \DateTimeImmutable());
    }
}
