<?php

namespace App\DTO\CartItem\Request;

use App\DTO\Common\PaginationQueryDto;
use Doctrine\Common\Collections\Order;

class CartItemQueryDto extends PaginationQueryDto
{
    /**
     * @var Order
     */
    public Order $order = Order::Descending;
}
