<?php

namespace App\Event;

use App\Entity\Order;
use Symfony\Contracts\EventDispatcher\Event;

class OrderPaidEvent extends Event
{
    /**
     * @param Order $order
     */
    public function __construct(
        private readonly Order $order
    ) {}

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }
}
