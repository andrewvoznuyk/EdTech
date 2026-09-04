<?php

namespace App\EventSubscriber;

use App\Entity\Asset;
use App\Event\OrderPaidEvent;
use App\Service\Interface\AssetFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class AssetFulfillmentSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<AssetFactoryInterface> $assetFactories
     */
    public function __construct(
        private iterable               $assetFactories,
        private EntityManagerInterface $entityManager
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPaidEvent::class => 'onOrderPaid',
        ];
    }

    public function onOrderPaid(OrderPaidEvent $event): void
    {
        $order = $event->getOrder();
        $user = $order->getUser();

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();

            $typeFromDb = $product->getAsset();

            if (!$typeFromDb) {
                continue;
            }

            $asset = $this->createAssetViaRegistry($typeFromDb);

            $asset->setUser($user);
            $asset->setProduct($product);
            $asset->setStatus(1);

            $item->setAsset($asset);

            $this->entityManager->persist($asset);
        }

        $this->entityManager->flush();
    }

    private function createAssetViaRegistry(string $type): Asset
    {
        foreach ($this->assetFactories as $factory) {
            if ($factory->supports($type)) {
                return $factory->create();
            }
        }

        throw new LogicException("No asset generator found for type: $type");
    }
}
