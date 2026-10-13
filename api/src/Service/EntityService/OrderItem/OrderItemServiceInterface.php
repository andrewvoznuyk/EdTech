<?php

namespace App\Service\EntityService\OrderItem;

use App\DTO\Common\PaginatedResult;
use App\DTO\OrderItem\Request\OrderItemQueryDto;
use App\Entity\OrderItem;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

interface OrderItemServiceInterface
{
    /**
     * @param User $user
     * @param Uuid $orderId
     * @return array<int, OrderItem>
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     */
    public function getItemsByOrderId(User $user, Uuid $orderId): array;

    /**
     * @param User $user
     * @param OrderItem $orderItem
     * @return OrderItem
     * @throws AccessDeniedHttpException
     */
    public function getItem(User $user, OrderItem $orderItem): OrderItem;

    /**
     * @param OrderItemQueryDto $orderItemQueryDto
     * @param User|null $currentUser
     * @return PaginatedResult
     */
    public function getCollection(OrderItemQueryDto $orderItemQueryDto, ?User $currentUser = null): PaginatedResult;
}
