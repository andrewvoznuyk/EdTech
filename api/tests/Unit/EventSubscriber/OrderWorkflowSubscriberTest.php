<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Asset;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Widget;
use App\Event\OrderPaidEvent;
use App\EventSubscriber\OrderWorkflowSubscriber;
use App\Service\Asset\OrderAssetServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class OrderWorkflowSubscriberTest extends TestCase
{
    /**
     * @return void
     */
    public function testGetSubscribedEvents(): void
    {
        $events = OrderWorkflowSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('workflow.order_lifecycle.completed.pay', $events);
        $this->assertArrayHasKey('workflow.order_lifecycle.completed.cancel', $events);
    }

    /**
     * @return void
     */
    public function testOnOrderPaidProvisionsAssetsAndDispatchesEvent(): void
    {
        $order = new Order();
        $orderAssetService = $this->createMock(OrderAssetServiceInterface::class);
        $orderAssetService->expects($this->once())
            ->method('createAssetsForOrder')
            ->with($order);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(OrderPaidEvent::class));

        $subscriber = new OrderWorkflowSubscriber($orderAssetService, $eventDispatcher);

        $transition = new Transition('pay', 'pending', 'paid');
        $completedEvent = new CompletedEvent($order, new Marking(['paid' => 1]), $transition);

        $subscriber->onOrderPaid($completedEvent);
    }

    /**
     * @return void
     */
    public function testOnOrderCancelledMarksActiveAssetsCancelled(): void
    {
        $order = new Order();
        $orderItem = new OrderItem();
        $asset = new Widget();
        $asset->setStatus(Asset::STATUS_ACTIVE);
        $orderItem->setAsset($asset);
        $order->addOrderItem($orderItem);

        $orderAssetService = $this->createMock(OrderAssetServiceInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $subscriber = new OrderWorkflowSubscriber($orderAssetService, $eventDispatcher);

        $transition = new Transition('cancel', 'pending', 'cancelled');
        $completedEvent = new CompletedEvent($order, new Marking(['cancelled' => 1]), $transition);

        $subscriber->onOrderCancelled($completedEvent);

        $this->assertSame(Asset::STATUS_CANCELLED, $asset->getStatus());
    }
}
