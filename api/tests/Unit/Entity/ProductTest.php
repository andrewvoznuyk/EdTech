<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Asset;
use App\Entity\CartItem;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Widget;
use App\Enum\AssetType;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaultValues(): void
    {
        $product = new Product();

        $this->assertNull($product->getTitle());
        $this->assertNull($product->getPrice());
        $this->assertNull($product->getAssetType());
        $this->assertSame(30, $product->getDurationDays());
        $this->assertNull($product->getOptions());
        $this->assertCount(0, $product->getCartItems());
        $this->assertCount(0, $product->getAssets());
        $this->assertCount(0, $product->getOrderItems());
    }

    /**
     * @return void
     */
    public function testSettersAndGetters(): void
    {
        $product = new Product();
        $product->setTitle('Standard Subscription');
        $product->setPrice('499.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(365);
        $product->setOptions(['theme' => 'dark', 'branding' => false]);

        $this->assertSame('Standard Subscription', $product->getTitle());
        $this->assertSame('499.00', $product->getPrice());
        $this->assertSame(AssetType::WIDGET->value, $product->getAssetType());
        $this->assertSame(365, $product->getDurationDays());
        $this->assertSame(['theme' => 'dark', 'branding' => false], $product->getOptions());
    }

    /**
     * @return void
     */
    public function testCartItemsAndOrderItemsCollections(): void
    {
        $product = new Product();

        $cartItem = new CartItem();
        $product->addCartItem($cartItem);
        $this->assertCount(1, $product->getCartItems());
        $this->assertSame($product, $cartItem->getProduct());

        $product->removeCartItem($cartItem);
        $this->assertCount(0, $product->getCartItems());
        $this->assertNull($cartItem->getProduct());

        $orderItem = new OrderItem();
        $product->addOrderItem($orderItem);
        $this->assertCount(1, $product->getOrderItems());
        $this->assertSame($product, $orderItem->getProduct());

        $product->removeOrderItem($orderItem);
        $this->assertCount(0, $product->getOrderItems());
        $this->assertNull($orderItem->getProduct());

        $widget = new Widget();
        $product->addAsset($widget);
        $this->assertCount(1, $product->getAssets());
        $this->assertSame($product, $widget->getProduct());

        $product->removeAsset($widget);
        $this->assertCount(0, $product->getAssets());
        $this->assertNull($widget->getProduct());
    }
}
