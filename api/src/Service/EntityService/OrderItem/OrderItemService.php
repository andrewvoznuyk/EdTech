<?php

namespace App\Service\EntityService\OrderItem;

use App\DTO\Common\PaginatedResult;
use App\DTO\OrderItem\Request\OrderItemQueryDto;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements OrderItemServiceInterface
 */
final readonly class OrderItemService implements OrderItemServiceInterface
{
    /**
     * @param OrderItemRepository $orderItemRepository
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        private OrderItemRepository $orderItemRepository,
        private OrderRepository $orderRepository,
    ) {
    }

    /**
     * @param User $user
     * @param Uuid $orderId
     * @return array<int, OrderItem>
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     */
    public function getItemsByOrderId(User $user, Uuid $orderId): array
    {
        /** @var Order|null $order */
        $order = $this->orderRepository->findOneBy(['id' => $orderId]);
        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        $isOwner = $order->getUser() === $user
            || ($order->getUser()?->getId() !== null && $order->getUser()->getId()->equals($user->getId()));
        $isAdmin = in_array(User::ROLE_ADMIN, $user->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return $order->getOrderItems()->getValues();
    }

    /**
     * @param User $user
     * @param OrderItem $orderItem
     * @return OrderItem
     * @throws AccessDeniedHttpException
     */
    public function getItem(User $user, OrderItem $orderItem): OrderItem
    {
        $order = $orderItem->getCustomerOrder();
        $isOwner = $order !== null && (
            $order->getUser() === $user
            || ($order->getUser()?->getId() !== null && $order->getUser()->getId()->equals($user->getId()))
        );
        $isAdmin = in_array(User::ROLE_ADMIN, $user->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return $orderItem;
    }

    /**
     * @param OrderItemQueryDto $orderItemQueryDto
     * @param User|null $currentUser
     * @return PaginatedResult
     */
    public function getCollection(OrderItemQueryDto $orderItemQueryDto, ?User $currentUser = null): PaginatedResult
    {
        return PaginatedResult::fromPaginator(
            $this->orderItemRepository->getFilteredPaginator($orderItemQueryDto, $currentUser),
            $orderItemQueryDto,
        );
    }
}
