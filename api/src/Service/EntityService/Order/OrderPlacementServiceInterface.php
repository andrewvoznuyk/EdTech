<?php

namespace App\Service\EntityService\Order;

use App\DTO\Common\PaginatedResult;
use App\DTO\Order\Request\OrderQueryDto;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

interface OrderPlacementServiceInterface
{
    /**
     * @param User $user
     * @return Order
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     */
    public function placeOrder(User $user): Order;

    /**
     * @param User $user
     * @param Order $order
     * @return Order
     * @throws AccessDeniedHttpException
     */
    public function getOrder(User $user, Order $order): Order;

    /**
     * @param OrderQueryDto $orderQueryDto
     * @param User|null $currentUser
     * @return PaginatedResult
     */
    public function getCollection(OrderQueryDto $orderQueryDto, ?User $currentUser = null): PaginatedResult;

    /**
     * @param User $user
     * @param Order $order
     * @return Order
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     */
    public function cancelOrder(User $user, Order $order): Order;

    /**
     * @param Order $order
     * @param OrderStatus $status
     * @return Order
     */
    public function updateStatus(Order $order, OrderStatus $status): Order;

    /**
     * @param Order $order
     * @return void
     */
    public function deleteOrder(Order $order): void;
}
