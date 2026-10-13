<?php

namespace App\Service\EntityService\Cart;

use App\DTO\Cart\Request\CartQueryDto;
use App\DTO\Common\PaginatedResult;
use App\Entity\Cart;
use App\Entity\User;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements CartServiceInterface
 */
final readonly class CartService implements CartServiceInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param CartRepository $cartRepository
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartRepository $cartRepository,
    ) {
    }

    /**
     * @param User $user
     * @return Cart
     */
    public function getCart(User $user): Cart
    {
        $cart = $user->getCart();

        if ($cart !== null) {
            return $cart;
        }

        return $this->create($user);
    }

    /**
     * @param User $user
     * @return Cart
     */
    public function create(User $user): Cart
    {
        $cart = new Cart();
        $cart->setUser($user);
        $user->setCart($cart);

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $cart;
    }

    /**
     * @param CartQueryDto $filterDto
     * @return PaginatedResult
     */
    public function getCollection(CartQueryDto $filterDto): PaginatedResult
    {
        return PaginatedResult::fromPaginator(
            $this->cartRepository->getFilteredPaginator($filterDto),
            $filterDto,
        );
    }

    /**
     * @param Cart $cart
     * @return void
     */
    public function clear(Cart $cart): void
    {
        foreach ($cart->getCartItems() as $item) {
            $this->entityManager->remove($item);
        }
        $cart->getCartItems()->clear();
    }
}
