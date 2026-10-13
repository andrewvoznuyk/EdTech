<?php

namespace App\Repository;

use App\DTO\OrderItem\Request\OrderItemQueryDto;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * @param OrderItemQueryDto $orderItemQueryDto
     * @param User|null $currentUser
     * @return Paginator<OrderItem>
     */
    public function getFilteredPaginator(OrderItemQueryDto $orderItemQueryDto, ?User $currentUser = null): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('orderItem')
            ->innerJoin('orderItem.customerOrder', 'customerOrder')
            ->addSelect('customerOrder')
            ->leftJoin('orderItem.product', 'product')
            ->addSelect('product')
            ->leftJoin('orderItem.asset', 'asset')
            ->addSelect('asset');

        if ($currentUser !== null) {
            $queryBuilder->andWhere('customerOrder.user = :currentUser')
                ->setParameter('currentUser', $currentUser->getId(), 'uuid');
        }

        if ($orderItemQueryDto->orderId !== null) {
            $queryBuilder->andWhere('customerOrder.id = :orderId')
                ->setParameter('orderId', $orderItemQueryDto->orderId, 'uuid');
        }

        if ($orderItemQueryDto->productId !== null) {
            $queryBuilder->andWhere('orderItem.product = :productId')
                ->setParameter('productId', $orderItemQueryDto->productId, 'uuid');
        }

        if ($orderItemQueryDto->assetId !== null) {
            $queryBuilder->andWhere('orderItem.asset = :assetId')
                ->setParameter('assetId', $orderItemQueryDto->assetId);
        }

        $queryBuilder->orderBy('orderItem.createdAt', $orderItemQueryDto->order->value)
            ->setFirstResult($orderItemQueryDto->getOffset())
            ->setMaxResults($orderItemQueryDto->limit);

        return new Paginator($queryBuilder->getQuery());
    }
}
