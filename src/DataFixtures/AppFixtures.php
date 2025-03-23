<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private SluggerInterface $slugger;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(SluggerInterface $slugger, UserPasswordHasherInterface $passwordHasher)
    {
        $this->slugger = $slugger;
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $om): void
    {
        // Créez l'utilisateur de démonstration
        $user = new User();
        $user->setEmail('demo@demo.com');
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demo'));
        $user->setUsername('demo');
        $user->setVerified(true);
        $om->persist($user);


        // Créez des catégories de démonstration
        $categories = ['Electronics', 'Books', 'Clothing', 'Home & Kitchen', 'Sports'];
        $categoryEntities = [];

        foreach ($categories as $categoryName) {
            $category = new Category();
            $category->setName($categoryName);
            $category->setSlug($this->slugger->slug($categoryName)->lower());
            $om->persist($category);
            $categoryEntities[] = $category;
        }

        // Créez des produits de démonstration
        for ($i = 1; $i <= 10; $i++) {
            $product = new Product();
            $product->setName('Produit ' . $i);
            $product->setSlug($this->slugger->slug('Produit ' . $i)->lower());
            $product->setBarCode('123456789' . $i);
            $product->setComment('Commentaire pour le produit ' . $i);
            $product->setSupplier('Fournisseur ' . $i);
            $product->setStock(10 * $i);
            $product->setPurchasePrice(10 * $i);
            $product->setSellingPrice(15 * $i);
            $product->updateMargin();
            $product->setUpdatedAt(new \DateTimeImmutable());

            // Associer une ou deux catégories aléatoires au produit
            $assignedCategories = (array) array_rand($categoryEntities, rand(1, 2));
            foreach ($assignedCategories as $categoryIndex) {
                $product->addCategory($categoryEntities[$categoryIndex]);
            }

            $om->persist($product);
        }

        $om->flush();
    }
}
