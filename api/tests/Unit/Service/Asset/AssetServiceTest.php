<?php

namespace App\Tests\Unit\Service\Asset;

use App\Entity\Asset;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Entity\Widget;
use App\Service\Asset\AssetFactoryResolver;
use App\Service\Asset\OrderAssetService;
use App\Service\Asset\WidgetAssetFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AssetServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testWidgetAssetFactory(): void
    {
        $factory = new WidgetAssetFactory();

        $this->assertTrue($factory->supports('widget'));
        $this->assertFalse($factory->supports('unknown'));

        $widget = $factory->create(['rate' => 5, 'view' => 120]);

        $this->assertInstanceOf(Widget::class, $widget);
        $this->assertSame(5, $widget->getRate());
        $this->assertSame(120, $widget->getView());
        $this->assertSame('widget', $widget->getAssetType());
    }

    /**
     * @return void
     */
    public function testOrderAssetServiceCreatesActiveAssetsForOrder(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Widget::class));

        $factory = new WidgetAssetFactory();
        $resolver = new AssetFactoryResolver([$factory]);
        $service = new OrderAssetService($resolver, $entityManager);

        $user = new User();
        $order = new Order();
        $order->setUser($user);

        $product = new Product();
        $product->setAssetType('widget');
        $product->setDurationDays(30);
        $product->setOptions(['rate' => 4, 'view' => 50]);

        $orderItem = new OrderItem();
        $orderItem->setProduct($product);
        $orderItem->setQuantity(2);
        $order->addOrderItem($orderItem);

        $assets = $service->createAssetsForOrder($order);

        $this->assertCount(1, $assets);
        $asset = $assets[0];
        $this->assertInstanceOf(Widget::class, $asset);
        $this->assertSame($user, $asset->getUser());
        $this->assertSame($product, $asset->getProduct());
        $this->assertSame(Asset::STATUS_ACTIVE, $asset->getStatus());
        $this->assertSame(60, $asset->getDurationDays());
        $this->assertNotNull($asset->getStartsAt());
        $this->assertNotNull($asset->getExpiresAt());
        $this->assertSame($asset, $orderItem->getAsset());
        $this->assertTrue($asset->isActive());
    }

    /**
     * @return void
     */
    public function testOrderAssetServiceIsIdempotentWhenAssetAlreadyLinked(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $factory = new WidgetAssetFactory();
        $resolver = new AssetFactoryResolver([$factory]);
        $service = new OrderAssetService($resolver, $entityManager);

        $order = new Order();
        $orderItem = new OrderItem();
        $existingAsset = new Widget();
        $orderItem->setAsset($existingAsset);
        $order->addOrderItem($orderItem);

        $assets = $service->createAssetsForOrder($order);

        $this->assertEmpty($assets);
    }
}
