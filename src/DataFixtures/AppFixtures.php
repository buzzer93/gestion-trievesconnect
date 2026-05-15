<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $om): void
    {
        // Utilisateur de démonstration
        $user = new User();
        $user->setEmail('demo@demo.com');
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demo'));
        $user->setUsername('demo');
        $user->setVerified(true);
        $om->persist($user);

        // Catégories
        $categoriesData = [
            ['name' => 'Épicerie', 'slug' => 'epicerie'],
            ['name' => 'Boissons', 'slug' => 'boissons'],
            ['name' => 'Hygiène', 'slug' => 'hygiene'],
            ['name' => 'Entretien', 'slug' => 'entretien'],
        ];

        $categories = [];
        foreach ($categoriesData as $data) {
            $category = new Category();
            $category->setName($data['name']);
            $category->setSlug($data['slug']);
            $om->persist($category);
            $categories[] = $category;
        }

        // Produits (couvre inventaire et étiquetage)
        $productsData = [
            [
                'name'          => 'Farine de blé T55',
                'slug'          => 'farine-de-ble-t55',
                'barCode'       => '3012345600001',
                'purchasePrice' => 0.60,
                'sellingPrice'  => 1.20,
                'stock'         => 50,
                'supplier'      => 'Moulin du Sud',
                'categoryIndex' => 0,
            ],
            [
                'name'          => 'Huile de tournesol 1L',
                'slug'          => 'huile-de-tournesol-1l',
                'barCode'       => '3012345600002',
                'purchasePrice' => 1.10,
                'sellingPrice'  => 2.20,
                'stock'         => 30,
                'supplier'      => 'Huilerie du Nord',
                'categoryIndex' => 0,
            ],
            [
                'name'          => 'Eau minérale 1.5L',
                'slug'          => 'eau-minerale-1-5l',
                'barCode'       => '3012345600003',
                'purchasePrice' => 0.25,
                'sellingPrice'  => 0.55,
                'stock'         => 120,
                'supplier'      => 'Source Pure',
                'categoryIndex' => 1,
            ],
            [
                'name'          => 'Jus d\'orange 1L',
                'slug'          => 'jus-d-orange-1l',
                'barCode'       => '3012345600004',
                'purchasePrice' => 0.90,
                'sellingPrice'  => 1.80,
                'stock'         => 40,
                'supplier'      => 'Fruits & Co',
                'categoryIndex' => 1,
            ],
            [
                'name'          => 'Gel douche 250ml',
                'slug'          => 'gel-douche-250ml',
                'barCode'       => '3012345600005',
                'purchasePrice' => 0.80,
                'sellingPrice'  => 1.95,
                'stock'         => 25,
                'supplier'      => 'Hygiène Pro',
                'categoryIndex' => 2,
            ],
            [
                'name'          => 'Liquide vaisselle 500ml',
                'slug'          => 'liquide-vaisselle-500ml',
                'barCode'       => '3012345600006',
                'purchasePrice' => 0.55,
                'sellingPrice'  => 1.25,
                'stock'         => 35,
                'supplier'      => 'CleanPlus',
                'categoryIndex' => 3,
            ],
        ];

        foreach ($productsData as $data) {
            $product = new Product();
            $product->setName($data['name']);
            $product->setSlug($data['slug']);
            $product->setBarCode($data['barCode']);
            $product->setPurchasePrice($data['purchasePrice']);
            $product->setSellingPrice($data['sellingPrice']);
            $product->setStock($data['stock']);
            $product->setSupplier($data['supplier']);
            $product->setComment('');
            $product->setUpdatedAt(new \DateTimeImmutable());

            $marginRate = $data['purchasePrice'] > 0
                ? round((($data['sellingPrice'] - $data['purchasePrice']) / $data['purchasePrice']) * 100, 2)
                : 0;
            $product->setMarginRate($marginRate);
            $product->setMarginAmount(round($data['sellingPrice'] - $data['purchasePrice'], 2));

            $product->addCategory($categories[$data['categoryIndex']]);
            $om->persist($product);
        }

        // Services
        $servicesData = [
            [
                'name'          => 'Recharge téléphone',
                'description'   => 'Recharge crédit téléphonique tous opérateurs',
                'slug'          => 'recharge-telephone',
                'barCode'       => '5012345600001',
                'sellingPrice'  => 5.00,
                'purchasePrice' => 0,
            ],
            [
                'name'          => 'Transfert d\'argent',
                'description'   => 'Service de transfert d\'argent national',
                'slug'          => 'transfert-argent',
                'barCode'       => '5012345600002',
                'sellingPrice'  => 3.00,
                'purchasePrice' => 0,
            ],
            [
                'name'          => 'Photocopie A4',
                'description'   => 'Photocopie noir et blanc format A4',
                'slug'          => 'photocopie-a4',
                'barCode'       => '5012345600003',
                'sellingPrice'  => 0.20,
                'purchasePrice' => 0,
            ],
        ];

        foreach ($servicesData as $data) {
            $service = new Service();
            $service->setName($data['name']);
            $service->setDescription($data['description']);
            $service->setSlug($data['slug']);
            $service->setBarCode($data['barCode']);
            $service->setSellingPrice($data['sellingPrice']);
            $service->setPurchasePrice($data['purchasePrice']);
            $om->persist($service);
        }

        // Clients
        $customersData = [
            [
                'name'        => 'Marie Dupont',
                'phoneNumber' => '0601020304',
                'address'     => '12 rue de la Paix',
                'postalCode'  => '38000',
                'city'        => 'Grenoble',
                'email'       => 'marie.dupont@example.com',
                'credits'     => 10,
            ],
            [
                'name'        => 'Ahmed Benali',
                'phoneNumber' => '0611223344',
                'address'     => '5 avenue des Alpes',
                'postalCode'  => '38250',
                'city'        => 'Villard-de-Lans',
                'email'       => 'ahmed.benali@example.com',
                'credits'     => 5,
            ],
            [
                'name'        => 'Sophie Martin',
                'phoneNumber' => '0622334455',
                'address'     => null,
                'postalCode'  => null,
                'city'        => 'Grenoble',
                'email'       => null,
                'credits'     => 0,
            ],
            [
                'name'        => 'Jean-Pierre Morel',
                'phoneNumber' => '0633445566',
                'address'     => '8 chemin du Bois',
                'postalCode'  => '38250',
                'city'        => 'Autrans',
                'email'       => 'jp.morel@example.com',
                'credits'     => 20,
            ],
        ];

        foreach ($customersData as $data) {
            $customer = new Customer();
            $customer->setName($data['name']);
            $customer->setPhoneNumber($data['phoneNumber']);
            if ($data['address'] !== null) {
                $customer->setAddress($data['address']);
            }
            if ($data['postalCode'] !== null) {
                $customer->setPostalCode($data['postalCode']);
            }
            if ($data['city'] !== null) {
                $customer->setCity($data['city']);
            }
            if ($data['email'] !== null) {
                $customer->setEmail($data['email']);
            }
            $customer->setCredits($data['credits']);
            $om->persist($customer);
        }

        $om->flush();
    }
}
