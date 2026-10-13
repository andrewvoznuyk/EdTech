<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Widget;
use PHPUnit\Framework\TestCase;

class OrderItemTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaultValues(): void
    {
        $orderItem = new OrderItem();

        $this->assertNull($orderItem->getCustomerOrder());
        $this->assertNull($orderItem->getProduct());
        $this->assertNull($orderItem->getAsset());
        $this->assertNull($orderItem->getQuantity());
        $this->assertNull($orderItem->getTotalPrice());
    }

    /**
     * @return void
     */
    public function testSettersAndGetters(): void
    {
        $orderItem = new OrderItem();
        $order = new Order();
        $product = new Product();
        $widget = new Widget();

        $orderItem->setCustomerOrder($order);
        $orderItem->setProduct($product);
        $orderItem->setAsset($widget);
        $orderItem->setQuantity(5);
        $orderItem->setTotalPrice('250.00');

        $this->assertSame($order, $orderItem->getCustomerOrder());
        $this->assertSame($product, $orderItem->getProduct());
        $this->assertSame($widget, $orderItem->getAsset());
        $this->assertSame(5, $orderItem->getQuantity());
        $this->assertSame('250.00', $orderItem->getTotalPrice());
    }

    /**
     * @return void
     */
    public function testCalculateTotalPrice(): void
    {
        $orderItem = new OrderItem();
        $product = new Product();
        $product->setPrice('45.50');

        $orderItem->setProduct($product);
        $orderItem->setQuantity(2);

        $orderItem->calculateTotalPrice();
        $this->assertSame('91.00', $orderItem->getTotalPrice());
    }
}
