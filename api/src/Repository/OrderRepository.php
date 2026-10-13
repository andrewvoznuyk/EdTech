<?php

namespace App\Repository;

use App\DTO\Order\Request\OrderQueryDto;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @param OrderQueryDto $orderQueryDto
     * @param User|null $currentUser
     * @return Paginator<Order>
     */
    public function getFilteredPaginator(OrderQueryDto $orderQueryDto, ?User $currentUser = null): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('customerOrder')
            ->leftJoin('customerOrder.user', 'user')
            ->addSelect('user')
            ->leftJoin('customerOrder.orderItems', 'orderItem')
            ->addSelect('orderItem')
            ->leftJoin('orderItem.product', 'product')
            ->addSelect('product');

        if ($currentUser !== null) {
            $queryBuilder->andWhere('customerOrder.user = :currentUser')
                ->setParameter('currentUser', $currentUser->getId(), 'uuid');
        } elseif ($orderQueryDto->userId !== null) {
            $queryBuilder->andWhere('customerOrder.user = :userId')
                ->setParameter('userId', $orderQueryDto->userId, 'uuid');
        }

        if ($orderQueryDto->status !== null) {
            $queryBuilder->andWhere('customerOrder.status = :status')
                ->setParameter('status', $orderQueryDto->status);
        }

        $queryBuilder->orderBy('customerOrder.createdAt', $orderQueryDto->order->value)
            ->setFirstResult($orderQueryDto->getOffset())
            ->setMaxResults($orderQueryDto->limit);

        return new Paginator($queryBuilder->getQuery());
    }
}
