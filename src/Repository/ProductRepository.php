<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\From;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

       /**
        * @return Product[] Returns an array of Product objects
        */
        public function findByCategory(int $categoryId,  string $order='ASC'): array
        {
            return $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->addSelect('c') // Inclut la catégorie dans le résultat
            ->where('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();
        }

        /**
        * @return Product[] Returns an array of Product objects
        */
       public function findAllByFieldAndOrder(string $field, string $order='ASC'): ?array
       {
           return $this->createQueryBuilder('p')
               ->orderBy('p'. '.' . $field,$order)
               ->getQuery()
               ->getResult()
           ;
       }
}
