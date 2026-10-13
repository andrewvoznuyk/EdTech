<?php

namespace App\Tests\Unit\Service\OrderItem;

use App\DTO\Common\PaginatedResult;
use App\DTO\OrderItem\Request\OrderItemQueryDto;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use App\Service\EntityService\OrderItem\OrderItemService;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

class OrderItemServiceTest extends TestCase
{
    /**
     * @var OrderItemRepository&MockObject
     */
    private OrderItemRepository&MockObject $orderItemRepository;

    /**
     * @var OrderRepository&MockObject
     */
    private OrderRepository&MockObject $orderRepository;

    /**
     * @var OrderItemService
     */
    private OrderItemService $orderItemService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);

        $this->orderItemService = new OrderItemService(
            $this->orderItemRepository,
            $this->orderRepository
        );
    }

    /**
     * @return void
     */
    public function testGetItemsByOrderIdSuccessForOwner(): void
    {
        $user = new User();
        $orderId = Uuid::v4();

        $order = new Order();
        $order->setUser($user);

        $item1 = new OrderItem();
        $order->addOrderItem($item1);

        $this->orderRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $orderId])
            ->willReturn($order);

        $items = $this->orderItemService->getItemsByOrderId($user, $orderId);

        $this->assertCount(1, $items);
        $this->assertSame($item1, $items[0]);
    }

    /**
     * @return void
     */
    public function testGetItemsByOrderIdSuccessForAdmin(): void
    {
        $owner = new User();
        $admin = new User();
        $admin->setRoles(['ROLE_ADMIN']);
        $orderId = Uuid::v4();

        $order = new Order();
        $order->setUser($owner);

        $this->orderRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $orderId])
            ->willReturn($order);

        $items = $this->orderItemService->getItemsByOrderId($admin, $orderId);

        $this->assertIsArray($items);
    }

    /**
     * @return void
     */
    public function testGetItemsByOrderIdThrowsNotFoundWhenOrderDoesNotExist(): void
    {
        $user = new User();
        $orderId = Uuid::v4();

        $this->orderRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $orderId])
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Order not found.');

        $this->orderItemService->getItemsByOrderId($user, $orderId);
    }

    /**
     * @return void
     */
    public function testGetItemsByOrderIdThrowsAccessDeniedForStranger(): void
    {
        $owner = new User();
        $stranger = new User();
        $orderId = Uuid::v4();

        $order = new Order();
        $order->setUser($owner);

        $this->orderRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $orderId])
            ->willReturn($order);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Access denied.');

        $this->orderItemService->getItemsByOrderId($stranger, $orderId);
    }

    /**
     * @return void
     */
    public function testGetItemSuccessForOwner(): void
    {
        $user = new User();
        $order = new Order();
        $order->setUser($user);

        $orderItem = new OrderItem();
        $orderItem->setCustomerOrder($order);

        $result = $this->orderItemService->getItem($user, $orderItem);

        $this->assertSame($orderItem, $result);
    }

    /**
     * @return void
     */
    public function testGetItemSuccessForAdmin(): void
    {
        $owner = new User();
        $admin = new User();
        $admin->setRoles(['ROLE_ADMIN']);

        $order = new Order();
        $order->setUser($owner);

        $orderItem = new OrderItem();
        $orderItem->setCustomerOrder($order);

        $result = $this->orderItemService->getItem($admin, $orderItem);

        $this->assertSame($orderItem, $result);
    }

    /**
     * @return void
     */
    public function testGetItemThrowsAccessDeniedForStranger(): void
    {
        $owner = new User();
        $stranger = new User();

        $order = new Order();
        $order->setUser($owner);

        $orderItem = new OrderItem();
        $orderItem->setCustomerOrder($order);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Access denied.');

        $this->orderItemService->getItem($stranger, $orderItem);
    }

    /**
     * @return void
     */
    public function testGetCollectionReturnsPaginatedResult(): void
    {
        $dto = new OrderItemQueryDto();
        $paginator = $this->createMock(Paginator::class);
        $paginator->method('count')->willReturn(0);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->orderItemRepository->expects($this->once())
            ->method('getFilteredPaginator')
            ->with($dto, null)
            ->willReturn($paginator);

        $result = $this->orderItemService->getCollection($dto);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }
}
