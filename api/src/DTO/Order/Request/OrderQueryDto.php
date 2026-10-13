<?php

namespace App\DTO\Order\Request;

use App\DTO\Common\PaginationQueryDto;
use App\Enum\OrderStatus;
use Doctrine\Common\Collections\Order;
use Symfony\Component\Validator\Constraints\Uuid;

class OrderQueryDto extends PaginationQueryDto
{
    /**
     * @var Order
     */
    public Order $order = Order::Descending;

    /**
     * @var OrderStatus|null
     */
    public ?OrderStatus $status = null;

    /**
     * @var string|null
     */
    #[Uuid]
    public ?string $userId = null;
}
