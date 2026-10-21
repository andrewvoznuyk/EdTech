<?php

namespace App\Service\EntityService\Order;

use App\DTO\Common\PaginatedResult;
use App\DTO\Order\Request\OrderQueryDto;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\EntityService\Cart\CartServiceInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @implements OrderPlacementServiceInterface
 */
final readonly class OrderPlacementService implements OrderPlacementServiceInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param OrderRepository $orderRepository
     * @param CartServiceInterface $cartService
     * @param WorkflowInterface $orderLifecycleStateMachine
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private CartServiceInterface $cartService,
        #[Target('order_lifecycle')]
        private WorkflowInterface $orderLifecycleStateMachine,
    ) {
    }

    /**
     * @param User $user
     * @return Order
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     */
    public function placeOrder(User $user): Order
    {
        if ($user->isDeleted()) {
            throw new AccessDeniedHttpException('User account is deactivated.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($user): Order {
            $cart = $this->cartService->getCart($user);
            $this->entityManager->lock($cart, LockMode::PESSIMISTIC_WRITE);

            if ($cart->getCartItems()->isEmpty()) {
                throw new BadRequestHttpException('Cart is empty.');
            }

            $order = new Order();
            $order->setUser($user);
            $order->setStatus(OrderStatus::PENDING);

            foreach ($cart->getCartItems() as $cartItem) {
                $product = $cartItem->getProduct();
                if ($product === null || $product->isDeleted()) {
                    throw new BadRequestHttpException('One or more products in cart are unavailable.');
                }

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($cartItem->getQuantity() ?? 1);
                $orderItem->calculateTotalPrice();
                $order->addOrderItem($orderItem);
            }

            $order->recalculateTotalPrice();

            $this->entityManager->persist($order);
            $this->cartService->clear($cart);
            $this->entityManager->flush();

            return $order;
        });
    }

    /**
     * @param User $user
     * @param Order $order
     * @return Order
     * @throws AccessDeniedHttpException
     */
    public function getOrder(User $user, Order $order): Order
    {
        $isOwner = $order->getUser() === $user
            || ($order->getUser()?->getId() !== null && $order->getUser()->getId()->equals($user->getId()));
        $isAdmin = in_array(User::ROLE_ADMIN, $user->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return $order;
    }

    /**
     * @param OrderQueryDto $orderQueryDto
     * @param User|null $currentUser
     * @return PaginatedResult
     */
    public function getCollection(OrderQueryDto $orderQueryDto, ?User $currentUser = null): PaginatedResult
    {
        return PaginatedResult::fromPaginator(
            $this->orderRepository->getFilteredPaginator($orderQueryDto, $currentUser),
            $orderQueryDto,
        );
    }

    /**
     * @param User $user
     * @param Order $order
     * @return Order
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     */
    public function cancelOrder(User $user, Order $order): Order
    {
        $isOwner = $order->getUser() === $user
            || ($order->getUser()?->getId() !== null && $order->getUser()->getId()->equals($user->getId()));
        $isAdmin = in_array(User::ROLE_ADMIN, $user->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($order): Order {
            if ($this->entityManager->contains($order)) {
                $this->entityManager->refresh($order);
                $this->entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
            }

            if ($order->getStatus() !== OrderStatus::PENDING || !$this->orderLifecycleStateMachine->can($order, 'cancel')) {
                throw new BadRequestHttpException('Only pending orders can be cancelled.');
            }

            $this->orderLifecycleStateMachine->apply($order, 'cancel');
            $this->entityManager->flush();

            return $order;
        });
    }

    /**
     * @param Order $order
     * @param OrderStatus $status
     * @return Order
     * @throws BadRequestHttpException
     */
    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        return $this->entityManager->wrapInTransaction(function () use ($order, $status): Order {
            if ($this->entityManager->contains($order)) {
                $this->entityManager->refresh($order);
                $this->entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
            }

            $currentStatus = $order->getStatus();
            if ($currentStatus === $status) {
                return $order;
            }

            $transition = match ($status) {
                OrderStatus::PAID => 'pay',
                OrderStatus::CANCELLED => 'cancel',
                default => null,
            };

            if ($transition === null || !$this->orderLifecycleStateMachine->can($order, $transition)) {
                throw new BadRequestHttpException(sprintf(
                    'Cannot transition order status from "%s" to "%s".',
                    $currentStatus->value,
                    $status->value,
                ));
            }

            $this->orderLifecycleStateMachine->apply($order, $transition);
            $this->entityManager->flush();

            return $order;
        });
    }

    /**
     * @param Order $order
     * @return void
     * @throws BadRequestHttpException
     */
    public function deleteOrder(Order $order): void
    {
        if ($order->getStatus() === OrderStatus::PAID) {
            throw new BadRequestHttpException('Cannot delete paid orders.');
        }

        $this->entityManager->remove($order);
        $this->entityManager->flush();
    }
}
