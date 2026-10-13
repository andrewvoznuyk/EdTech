<?php

namespace App\Service\EntityService\Cart;

use App\DTO\Cart\Request\CartQueryDto;
use App\DTO\Common\PaginatedResult;
use App\Entity\Cart;
use App\Entity\User;

interface CartServiceInterface
{
    /**
     * @param User $user
     * @return Cart
     */
    public function getCart(User $user): Cart;

    /**
     * @param User $user
     * @return Cart
     */
    public function create(User $user): Cart;

    /**
     * @param CartQueryDto $filterDto
     * @return PaginatedResult
     */
    public function getCollection(CartQueryDto $filterDto): PaginatedResult;

    /**
     * @param Cart $cart
     * @return void
     */
    public function clear(Cart $cart): void;
}
