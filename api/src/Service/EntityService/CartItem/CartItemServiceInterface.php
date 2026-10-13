<?php

namespace App\Service\EntityService\CartItem;

use App\DTO\CartItem\Request\CartItemQueryDto;
use App\DTO\Common\PaginatedResult;
use App\Entity\CartItem;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

interface CartItemServiceInterface
{
    /**
     * @param User $user
     * @param CartItem $cartItem
     * @return CartItem
     * @throws BadRequestHttpException
     */
    public function addItem(User $user, CartItem $cartItem): CartItem;

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @param int $quantity
     * @return CartItem
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     */
    public function updateQuantity(User $user, CartItem $cartItem, int $quantity): CartItem;

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @return void
     * @throws AccessDeniedHttpException
     */
    public function removeItem(User $user, CartItem $cartItem): void;

    /**
     * @param CartItemQueryDto $filterDto
     * @return PaginatedResult
     */
    public function getCollection(CartItemQueryDto $filterDto): PaginatedResult;
}
