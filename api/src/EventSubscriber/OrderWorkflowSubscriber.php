<?php

namespace App\EventSubscriber;

use App\Entity\Asset;
use App\Entity\Order;
use App\Event\OrderPaidEvent;
use App\Service\Asset\OrderAssetServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * @implements EventSubscriberInterface
 */
class OrderWorkflowSubscriber implements EventSubscriberInterface
{
    /**
     * @param OrderAssetServiceInterface $orderAssetService
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        private readonly OrderAssetServiceInterface $orderAssetService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.order_lifecycle.completed.pay' => 'onOrderPaid',
            'workflow.order_lifecycle.completed.cancel' => 'onOrderCancelled',
        ];
    }

    /**
     * @param CompletedEvent $event
     * @return void
     */
    public function onOrderPaid(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof Order) {
            return;
        }

        $this->orderAssetService->createAssetsForOrder($subject);
        $this->eventDispatcher->dispatch(new OrderPaidEvent($subject));
    }

    /**
     * @param CompletedEvent $event
     * @return void
     */
    public function onOrderCancelled(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof Order) {
            return;
        }

        foreach ($subject->getOrderItems() as $orderItem) {
            $asset = $orderItem->getAsset();
            if ($asset !== null && $asset->getStatus() === Asset::STATUS_ACTIVE) {
                $asset->setStatus(Asset::STATUS_CANCELLED);
            }
        }
    }
}
