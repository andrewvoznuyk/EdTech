<?php

namespace App\Service\EntityService\CartItem;

use App\DTO\CartItem\Request\CartItemQueryDto;
use App\DTO\Common\PaginatedResult;
use App\Entity\CartItem;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Service\EntityService\Cart\CartServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements CartItemServiceInterface
 */
final readonly class CartItemService implements CartItemServiceInterface
{
    public const int MAX_ITEM_QUANTITY = 99;

    /**
     * @param EntityManagerInterface $entityManager
     * @param CartItemRepository $cartItemRepository
     * @param CartServiceInterface $cartService
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartItemRepository $cartItemRepository,
        private CartServiceInterface $cartService,
    ) {
    }

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @return CartItem
     * @throws BadRequestHttpException
     */
    public function addItem(User $user, CartItem $cartItem): CartItem
    {
        $product = $cartItem->getProduct();
        if (null === $product || $product->isDeleted()) {
            throw new BadRequestHttpException('Product is unavailable or deleted.');
        }

        $cart = $this->cartService->getCart($user);
        $requestedQuantity = $cartItem->getQuantity() ?? 1;

        $existingItem = $this->cartItemRepository->findOneBy([
            'cart' => $cart,
            'product' => $product,
        ]);

        if ($existingItem !== null) {
            $newQuantity = $existingItem->getQuantity() + $requestedQuantity;
            if ($newQuantity > self::MAX_ITEM_QUANTITY) {
                throw new BadRequestHttpException(sprintf('Maximum quantity of %d items reached for this product.', self::MAX_ITEM_QUANTITY));
            }

            $existingItem->setQuantity($newQuantity);
            $this->entityManager->flush();

            return $existingItem;
        }

        if ($requestedQuantity > self::MAX_ITEM_QUANTITY) {
            throw new BadRequestHttpException(sprintf('Maximum quantity of %d items reached for this product.', self::MAX_ITEM_QUANTITY));
        }

        $cartItem->setQuantity($requestedQuantity);
        $cart->addCartItem($cartItem);
        $this->entityManager->persist($cartItem);
        $this->entityManager->flush();

        return $cartItem;
    }

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @param int $quantity
     * @return CartItem
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     */
    public function updateQuantity(User $user, CartItem $cartItem, int $quantity): CartItem
    {
        if ($cartItem->getCart()?->getUser() !== $user) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        if ($quantity > self::MAX_ITEM_QUANTITY) {
            throw new BadRequestHttpException(sprintf('Maximum quantity of %d items reached for this product.', self::MAX_ITEM_QUANTITY));
        }

        $cartItem->setQuantity($quantity);
        $this->entityManager->flush();

        return $cartItem;
    }

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @return void
     * @throws AccessDeniedHttpException
     */
    public function removeItem(User $user, CartItem $cartItem): void
    {
        if ($cartItem->getCart()?->getUser() !== $user) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        $cart = $cartItem->getCart();
        $cart?->removeCartItem($cartItem);

        $this->entityManager->remove($cartItem);
        $this->entityManager->flush();
    }

    /**
     * @param CartItemQueryDto $filterDto
     * @return PaginatedResult
     */
    public function getCollection(CartItemQueryDto $filterDto): PaginatedResult
    {
        return PaginatedResult::fromPaginator(
            $this->cartItemRepository->getFilteredPaginator($filterDto),
            $filterDto,
        );
    }
}
