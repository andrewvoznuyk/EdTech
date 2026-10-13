<?php

namespace App\DTO\OrderItem\Request;

use App\DTO\Common\PaginationQueryDto;
use Doctrine\Common\Collections\Order;
use Symfony\Component\Uid\Uuid;

class OrderItemQueryDto extends PaginationQueryDto
{
    /**
     * @var Order
     */
    public Order $order = Order::Descending;

    /**
     * @var Uuid|null
     */
    public ?Uuid $orderId = null;

    /**
     * @var Uuid|null
     */
    public ?Uuid $productId = null;

    /**
     * @var int|null
     */
    public ?int $assetId = null;
}
