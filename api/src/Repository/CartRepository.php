<?php

namespace App\Repository;

use App\DTO\Cart\Request\CartQueryDto;
use App\Entity\Cart;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    /**
     * @param CartQueryDto $cartQueryDto
     * @param User|null $currentUser
     * @return Paginator<Cart>
     */
    public function getFilteredPaginator(CartQueryDto $cartQueryDto, ?User $currentUser = null): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('cart');

        if ($currentUser !== null) {
            $queryBuilder->andWhere('cart.user = :currentUser')
                ->setParameter('currentUser', $currentUser->getId(), 'uuid');
        }

        $queryBuilder->orderBy('cart.createdAt', $cartQueryDto->order->value)
            ->setFirstResult($cartQueryDto->getOffset())
            ->setMaxResults($cartQueryDto->limit);

        return new Paginator($queryBuilder->getQuery());
    }
}
