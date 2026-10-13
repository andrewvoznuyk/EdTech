<?php

namespace App\Tests\Unit\Service\Order;

use App\DTO\Common\PaginatedResult;
use App\DTO\Order\Request\OrderQueryDto;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\EntityService\Cart\CartServiceInterface;
use App\Service\EntityService\Order\OrderPlacementService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderPlacementServiceTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var OrderRepository&MockObject
     */
    private OrderRepository&MockObject $orderRepository;

    /**
     * @var CartServiceInterface&MockObject
     */
    private CartServiceInterface&MockObject $cartService;

    /**
     * @var OrderPlacementService
     */
    private OrderPlacementService $orderPlacementService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('wrapInTransaction')
            ->willReturnCallback(fn(callable $callback) => $callback());

        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->cartService = $this->createMock(CartServiceInterface::class);

        $this->orderPlacementService = new OrderPlacementService(
            $this->entityManager,
            $this->orderRepository,
            $this->cartService
        );
    }

    /**
     * @return void
     */
    public function testPlaceOrderSuccess(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);

        $product = new Product();
        $product->setPrice('50.00');

        $cartItem = new CartItem();
        $cartItem->setProduct($product);
        $cartItem->setQuantity(2);
        $cart->addCartItem($cartItem);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($user)
            ->willReturn($cart);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Order::class));

        $this->cartService->expects($this->once())
            ->method('clear')
            ->with($cart);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $order = $this->orderPlacementService->placeOrder($user);

        $this->assertSame($user, $order->getUser());
        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $this->assertSame('100.00', $order->getTotalPrice());
        $this->assertCount(1, $order->getOrderItems());
    }

    /**
     * @return void
     */
    public function testPlaceOrderThrowsBadRequestWhenCartIsEmpty(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($user)
            ->willReturn($cart);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cart is empty.');

        $this->orderPlacementService->placeOrder($user);
    }

    /**
     * @return void
     */
    public function testPlaceOrderThrowsBadRequestWhenProductIsDeleted(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);

        $product = new Product();
        $product->setPrice('20.00');
        $product->setDeletedAt(new DateTimeImmutable());

        $cartItem = new CartItem();
        $cartItem->setProduct($product);
        $cartItem->setQuantity(1);
        $cart->addCartItem($cartItem);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($user)
            ->willReturn($cart);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('One or more products in cart are unavailable.');

        $this->orderPlacementService->placeOrder($user);
    }

    /**
     * @return void
     */
    public function testPlaceOrderThrowsAccessDeniedWhenUserIsDeleted(): void
    {
        $user = new User();
        $user->setDeletedAt(new DateTimeImmutable());

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('User account is deactivated.');

        $this->orderPlacementService->placeOrder($user);
    }

    /**
     * @return void
     */
    public function testGetOrderSuccessForOwner(): void
    {
        $owner = new User();
        $order = new Order();
        $order->setUser($owner);

        $result = $this->orderPlacementService->getOrder($owner, $order);

        $this->assertSame($order, $result);
    }

    /**
     * @return void
     */
    public function testGetOrderSuccessForAdmin(): void
    {
        $owner = new User();
        $admin = new User();
        $admin->setRoles(['ROLE_ADMIN']);

        $order = new Order();
        $order->setUser($owner);

        $result = $this->orderPlacementService->getOrder($admin, $order);

        $this->assertSame($order, $result);
    }

    /**
     * @return void
     */
    public function testGetOrderThrowsAccessDeniedForStranger(): void
    {
        $owner = new User();
        $stranger = new User();

        $order = new Order();
        $order->setUser($owner);

        $this->expectException(AccessDeniedHttpException::class);

        $this->orderPlacementService->getOrder($stranger, $order);
    }

    /**
     * @return void
     */
    public function testCancelOrderSuccess(): void
    {
        $owner = new User();
        $order = new Order();
        $order->setUser($owner);
        $order->setStatus(OrderStatus::PENDING);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->orderPlacementService->cancelOrder($owner, $order);

        $this->assertSame(OrderStatus::CANCELLED, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testCancelOrderThrowsAccessDeniedForStranger(): void
    {
        $owner = new User();
        $stranger = new User();

        $order = new Order();
        $order->setUser($owner);

        $this->expectException(AccessDeniedHttpException::class);

        $this->orderPlacementService->cancelOrder($stranger, $order);
    }

    /**
     * @return void
     */
    public function testCancelOrderThrowsBadRequestWhenNotPending(): void
    {
        $owner = new User();
        $order = new Order();
        $order->setUser($owner);
        $order->setStatus(OrderStatus::CANCELLED);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Only pending orders can be cancelled.');

        $this->orderPlacementService->cancelOrder($owner, $order);
    }

    /**
     * @return void
     */
    public function testUpdateStatusSuccess(): void
    {
        $order = new Order();
        $order->setStatus(OrderStatus::PENDING);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->orderPlacementService->updateStatus($order, OrderStatus::PAID);

        $this->assertSame(OrderStatus::PAID, $result->getStatus());
    }

    /**
     * @return void
     */
    public function testDeleteOrderSuccess(): void
    {
        $order = new Order();
        $order->setStatus(OrderStatus::PENDING);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($order);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->orderPlacementService->deleteOrder($order);
    }

    /**
     * @return void
     */
    public function testUpdateStatusThrowsWhenInvalidTransition(): void
    {
        $order = new Order();
        $order->setStatus(OrderStatus::CANCELLED);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot transition order status from "cancelled" to "paid".');

        $this->orderPlacementService->updateStatus($order, OrderStatus::PAID);
    }

    /**
     * @return void
     */
    public function testDeleteOrderThrowsWhenOrderIsPaid(): void
    {
        $order = new Order();
        $order->setStatus(OrderStatus::PAID);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot delete paid orders.');

        $this->orderPlacementService->deleteOrder($order);
    }
}
