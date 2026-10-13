<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
    /**
     * @return void
     */
    public function testInitialValues(): void
    {
        $cart = new Cart();

        $this->assertNull($cart->getUser());
        $this->assertCount(0, $cart->getCartItems());
        $this->assertSame('0.00', $cart->getTotalPrice());
        $this->assertSame(0, $cart->getTotalQuantity());
        $this->assertNull($cart->getCreatedAt());
    }

    /**
     * @return void
     */
    public function testSetUser(): void
    {
        $cart = new Cart();
        $user = new User();

        $cart->setUser($user);

        $this->assertSame($user, $cart->getUser());

        $cart->setUser(null);
        $this->assertNull($cart->getUser());
    }

    /**
     * @return void
     */
    public function testAddAndRemoveCartItem(): void
    {
        $cart = new Cart();
        $cartItem = new CartItem();

        $cart->addCartItem($cartItem);

        $this->assertCount(1, $cart->getCartItems());
        $this->assertTrue($cart->getCartItems()->contains($cartItem));
        $this->assertSame($cart, $cartItem->getCart());

        // Adding the same item again should not duplicate
        $cart->addCartItem($cartItem);
        $this->assertCount(1, $cart->getCartItems());

        $cart->removeCartItem($cartItem);
        $this->assertCount(0, $cart->getCartItems());
        $this->assertNull($cartItem->getCart());
    }

    /**
     * @return void
     */
    public function testGetTotalPriceAndQuantity(): void
    {
        $cart = new Cart();

        $product1 = new Product();
        $product1->setPrice('15.50');

        $item1 = new CartItem();
        $item1->setProduct($product1);
        $item1->setQuantity(2); // 31.00

        $product2 = new Product();
        $product2->setPrice('10.25');

        $item2 = new CartItem();
        $item2->setProduct($product2);
        $item2->setQuantity(3); // 30.75

        $cart->addCartItem($item1);
        $cart->addCartItem($item2);

        $this->assertSame(5, $cart->getTotalQuantity());
        $this->assertSame('61.75', $cart->getTotalPrice());
    }
}
