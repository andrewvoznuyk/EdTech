<?php

namespace App\DTO\Cart\Request;

use App\DTO\Common\PaginationQueryDto;
use Doctrine\Common\Collections\Order;

class CartQueryDto extends PaginationQueryDto
{
    /**
     * @var Order
     */
    public Order $order = Order::Descending;
}
