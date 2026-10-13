<?php

namespace App\Repository;

use App\DTO\CartItem\Request\CartItemQueryDto;
use App\Entity\CartItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /**
     * @param CartItemQueryDto $cartItemQueryDto
     * @param User|null $currentUser
     * @return Paginator<CartItem>
     */
    public function getFilteredPaginator(CartItemQueryDto $cartItemQueryDto, ?User $currentUser = null): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('cartItem');

        if ($currentUser !== null) {
            $queryBuilder->innerJoin('cartItem.cart', 'cart')
                ->andWhere('cart.user = :currentUser')
                ->setParameter('currentUser', $currentUser->getId(), 'uuid');
        }

        $queryBuilder->orderBy('cartItem.createdAt', $cartItemQueryDto->order->value)
            ->setFirstResult($cartItemQueryDto->getOffset())
            ->setMaxResults($cartItemQueryDto->limit);

        return new Paginator($queryBuilder->getQuery());
    }
}
