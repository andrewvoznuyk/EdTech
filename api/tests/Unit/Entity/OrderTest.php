<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaultValues(): void
    {
        $order = new Order();

        $this->assertNull($order->getUser());
        $this->assertNull($order->getTotalPrice());
        $this->assertCount(0, $order->getOrderItems());
    }

    /**
     * @return void
     */
    public function testUserAssociation(): void
    {
        $order = new Order();
        $user = new User();

        $order->setUser($user);
        $this->assertSame($user, $order->getUser());

        $order->setUser(null);
        $this->assertNull($order->getUser());
    }

    /**
     * @return void
     */
    public function testAddAndRemoveOrderItems(): void
    {
        $order = new Order();
        $orderItem = new OrderItem();

        $order->addOrderItem($orderItem);
        $this->assertCount(1, $order->getOrderItems());
        $this->assertSame($order, $orderItem->getCustomerOrder());

        // Re-adding same item should not duplicate
        $order->addOrderItem($orderItem);
        $this->assertCount(1, $order->getOrderItems());

        $order->removeOrderItem($orderItem);
        $this->assertCount(0, $order->getOrderItems());
        $this->assertNull($orderItem->getCustomerOrder());
    }

    /**
     * @return void
     */
    public function testRecalculateTotalPrice(): void
    {
        $order = new Order();

        $product1 = new Product();
        $product1->setPrice('100.00');

        $item1 = new OrderItem();
        $item1->setProduct($product1);
        $item1->setQuantity(2);
        $item1->calculateTotalPrice(); // 200.00

        $product2 = new Product();
        $product2->setPrice('50.25');

        $item2 = new OrderItem();
        $item2->setProduct($product2);
        $item2->setQuantity(3);
        $item2->calculateTotalPrice(); // 150.75

        $order->addOrderItem($item1);
        $order->addOrderItem($item2);

        $order->recalculateTotalPrice();
        $this->assertSame('350.75', $order->getTotalPrice());
    }
}
