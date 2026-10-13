<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class CartItemTest extends TestCase
{
    /**
     * @return void
     */
    public function testInitialValues(): void
    {
        $cartItem = new CartItem();

        $this->assertNull($cartItem->getCart());
        $this->assertNull($cartItem->getProduct());
        $this->assertSame(1, $cartItem->getQuantity());
        $this->assertSame('0.00', $cartItem->getTotalPrice());
    }

    /**
     * @return void
     */
    public function testSetCartAndProduct(): void
    {
        $cartItem = new CartItem();
        $cart = new Cart();
        $product = new Product();

        $cartItem->setCart($cart);
        $cartItem->setProduct($product);
        $cartItem->setQuantity(4);

        $this->assertSame($cart, $cartItem->getCart());
        $this->assertSame($product, $cartItem->getProduct());
        $this->assertSame(4, $cartItem->getQuantity());
    }

    /**
     * @return void
     */
    public function testGetTotalPriceWithProduct(): void
    {
        $cartItem = new CartItem();
        $product = new Product();
        $product->setPrice('29.99');

        $cartItem->setProduct($product);
        $cartItem->setQuantity(3);

        $this->assertSame('89.97', $cartItem->getTotalPrice());
    }

    /**
     * @return void
     */
    public function testGetTotalPriceWithoutProduct(): void
    {
        $cartItem = new CartItem();
        $cartItem->setQuantity(5);

        $this->assertSame('0.00', $cartItem->getTotalPrice());
    }

    /**
     * @return void
     */
    public function testGetTotalPriceWithSoftDeletedProduct(): void
    {
        $cartItem = new CartItem();
        $product = new Product();
        $product->setPrice('50.00');
        $product->setDeletedAt(new \DateTimeImmutable());

        $cartItem->setProduct($product);
        $cartItem->setQuantity(2);

        $this->assertSame('0.00', $cartItem->getTotalPrice());
    }
}
