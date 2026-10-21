<?php

namespace App\Tests\Unit\Controller;

use App\Controller\PaymentWebhookController;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\EntityService\Order\OrderPlacementServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentWebhookControllerUnitTest extends TestCase
{
    /**
     * @return void
     */
    public function testHandleByIdWithValidOrder(): void
    {
        $orderUuid = Uuid::v7();
        $order = new Order();
        $order->setStatus(OrderStatus::PENDING);
        $reflection = new ReflectionProperty(Order::class, 'id');
        $reflection->setValue($order, $orderUuid);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $orderUuid])
            ->willReturn($order);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Order::class)
            ->willReturn($orderRepository);

        $orderPlacementService = $this->createMock(OrderPlacementServiceInterface::class);
        $orderPlacementService->expects($this->once())
            ->method('updateStatus')
            ->with($order, OrderStatus::PAID)
            ->willReturn($order);

        $controller = new PaymentWebhookController(
            $entityManager,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $orderPlacementService
        );

        $response = $controller->handleById($orderUuid->toRfc4122());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @return void
     */
    public function testHandleByPayloadWithValidOrder(): void
    {
        $orderUuid = Uuid::v7();
        $order = new Order();
        $order->setStatus(OrderStatus::PENDING);
        $reflection = new ReflectionProperty(Order::class, 'id');
        $reflection->setValue($order, $orderUuid);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $orderUuid])
            ->willReturn($order);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Order::class)
            ->willReturn($orderRepository);

        $orderPlacementService = $this->createMock(OrderPlacementServiceInterface::class);
        $orderPlacementService->expects($this->once())
            ->method('updateStatus')
            ->with($order, OrderStatus::PAID)
            ->willReturn($order);

        $controller = new PaymentWebhookController(
            $entityManager,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $orderPlacementService
        );

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['order_id' => $orderUuid->toRfc4122()]));
        $response = $controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @return void
     */
    public function testHandleWhenOrderNotFoundWithValidUuidReturnsEmptyJson(): void
    {
        $orderUuid = Uuid::v7();

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $orderUuid])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Order::class)
            ->willReturn($orderRepository);

        $orderPlacementService = $this->createMock(OrderPlacementServiceInterface::class);
        $orderPlacementService->expects($this->never())->method('updateStatus');

        $controller = new PaymentWebhookController(
            $entityManager,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $orderPlacementService
        );

        $response = $controller->handleById($orderUuid->toRfc4122());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('[]', $response->getContent());
    }

    /**
     * @return void
     */
    public function testHandleWhenInvalidUuidReturnsEmptyJsonWithoutQueryingDatabase(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');

        $orderPlacementService = $this->createMock(OrderPlacementServiceInterface::class);
        $orderPlacementService->expects($this->never())->method('updateStatus');

        $controller = new PaymentWebhookController(
            $entityManager,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $orderPlacementService
        );

        $response = $controller->handleById('invalid-uuid-string');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('[]', $response->getContent());
    }
}
