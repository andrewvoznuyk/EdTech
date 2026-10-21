<?php

namespace App\Service\Asset;

use App\Entity\Asset;
use App\Entity\Order;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements OrderAssetServiceInterface
 */
class OrderAssetService implements OrderAssetServiceInterface
{
    /**
     * @param AssetFactoryResolverInterface $assetFactoryResolver
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private readonly AssetFactoryResolverInterface $assetFactoryResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Order $order
     * @return array<Asset>
     */
    public function createAssetsForOrder(Order $order): array
    {
        $createdAssets = [];
        $user = $order->getUser();

        foreach ($order->getOrderItems() as $orderItem) {
            if ($orderItem->getAsset() !== null) {
                continue;
            }

            $product = $orderItem->getProduct();
            if ($product === null) {
                continue;
            }

            $assetType = $product->getAssetType() ?? 'widget';
            $durationDaysPerUnit = $product->getDurationDays() ?? 30;
            $quantity = $orderItem->getQuantity() ?? 1;
            $totalDurationDays = $durationDaysPerUnit * $quantity;

            $asset = $this->assetFactoryResolver->createAsset(
                $assetType,
                $product->getOptions() ?? []
            );

            $startsAt = new DateTimeImmutable();
            $expiresAt = $startsAt->modify(sprintf('+%d days', $totalDurationDays));

            $asset->setUser($user);
            $asset->setProduct($product);
            $asset->setStatus(Asset::STATUS_ACTIVE);
            $asset->setDurationDays($totalDurationDays);
            $asset->setStartsAt($startsAt);
            $asset->setExpiresAt($expiresAt);

            $orderItem->setAsset($asset);
            $this->entityManager->persist($asset);

            $createdAssets[] = $asset;
        }

        return $createdAssets;
    }
}
