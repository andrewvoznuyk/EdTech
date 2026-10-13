<?php

namespace App\Repository;

use App\DTO\Product\Request\ProductQueryDto;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param ProductQueryDto $productQueryDto
     * @return Paginator<Product>
     */
    public function getFilteredPaginator(ProductQueryDto $productQueryDto): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('product');

        if (!empty($productQueryDto->title)) {
            $queryBuilder->andWhere('product.title LIKE :title')
                ->setParameter('title', '%' . $productQueryDto->title . '%');
        }

        if (!empty($productQueryDto->assetType)) {
            $queryBuilder->andWhere('product.assetType = :assetType')
                ->setParameter('assetType', $productQueryDto->assetType);
        }

        if (!empty($productQueryDto->minPrice)) {
            $queryBuilder->andWhere('product.price >= :minPrice')
                ->setParameter('minPrice', $productQueryDto->minPrice);
        }

        $queryBuilder->orderBy('product.createdAt', $productQueryDto->order->value)
            ->setFirstResult($productQueryDto->getOffset())
            ->setMaxResults($productQueryDto->limit);

        return new Paginator($queryBuilder->getQuery());
    }
}
