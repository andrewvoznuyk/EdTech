<?php

namespace App\Tests\Unit\Service\CartItem;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Service\EntityService\Cart\CartServiceInterface;
use App\Service\EntityService\CartItem\CartItemService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CartItemServiceTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var CartItemRepository&MockObject
     */
    private CartItemRepository&MockObject $cartItemRepository;

    /**
     * @var CartServiceInterface&MockObject
     */
    private CartServiceInterface&MockObject $cartService;

    /**
     * @var CartItemService
     */
    private CartItemService $cartItemService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cartItemRepository = $this->createMock(CartItemRepository::class);
        $this->cartService = $this->createMock(CartServiceInterface::class);

        $this->cartItemService = new CartItemService(
            $this->entityManager,
            $this->cartItemRepository,
            $this->cartService
        );
    }

    /**
     * @return void
     */
    public function testAddItemWhenItemDoesNotExistCreatesNew(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);
        $product = new Product();

        $cartItem = new CartItem();
        $cartItem->setProduct($product);
        $cartItem->setQuantity(2);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($user)
            ->willReturn($cart);

        $this->cartItemRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'cart' => $cart,
                'product' => $product,
            ])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($cartItem);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->cartItemService->addItem($user, $cartItem);

        $this->assertSame($cartItem, $result);
        $this->assertSame($cart, $cartItem->getCart());
        $this->assertSame(2, $cartItem->getQuantity());
    }

    /**
     * @return void
     */
    public function testAddItemWhenItemAlreadyExistsIncrementsQuantity(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);
        $product = new Product();

        $incomingCartItem = new CartItem();
        $incomingCartItem->setProduct($product);
        $incomingCartItem->setQuantity(3);

        $existingItem = new CartItem();
        $existingItem->setCart($cart);
        $existingItem->setProduct($product);
        $existingItem->setQuantity(2);

        $this->cartService->expects($this->once())
            ->method('getCart')
            ->with($user)
            ->willReturn($cart);

        $this->cartItemRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'cart' => $cart,
                'product' => $product,
            ])
            ->willReturn($existingItem);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->cartItemService->addItem($user, $incomingCartItem);

        $this->assertSame($existingItem, $result);
        $this->assertSame(5, $existingItem->getQuantity());
    }

    /**
     * @return void
     */
    public function testUpdateQuantitySuccess(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);

        $cartItem = new CartItem();
        $cartItem->setCart($cart);
        $cartItem->setQuantity(1);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->cartItemService->updateQuantity($user, $cartItem, 5);

        $this->assertSame($cartItem, $result);
        $this->assertSame(5, $cartItem->getQuantity());
    }

    /**
     * @return void
     */
    public function testUpdateQuantityThrowsAccessDeniedWhenNotOwner(): void
    {
        $owner = new User();
        $stranger = new User();
        $cart = new Cart();
        $cart->setUser($owner);

        $cartItem = new CartItem();
        $cartItem->setCart($cart);

        $this->expectException(AccessDeniedHttpException::class);

        $this->cartItemService->updateQuantity($stranger, $cartItem, 5);
    }

    /**
     * @return void
     */
    public function testRemoveItemSuccess(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);

        $cartItem = new CartItem();
        $cartItem->setCart($cart);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($cartItem);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->cartItemService->removeItem($user, $cartItem);
    }

    /**
     * @return void
     */
    public function testRemoveItemThrowsAccessDeniedWhenNotOwner(): void
    {
        $owner = new User();
        $stranger = new User();
        $cart = new Cart();
        $cart->setUser($owner);

        $cartItem = new CartItem();
        $cartItem->setCart($cart);

        $this->expectException(AccessDeniedHttpException::class);

        $this->cartItemService->removeItem($stranger, $cartItem);
    }

    /**
     * @return void
     */
    public function testAddItemThrowsBadRequestWhenProductIsNull(): void
    {
        $user = new User();
        $cartItem = new CartItem();

        $this->expectException(BadRequestHttpException::class);
        $this->cartItemService->addItem($user, $cartItem);
    }

    /**
     * @return void
     */
    public function testAddItemThrowsBadRequestWhenProductIsDeleted(): void
    {
        $user = new User();
        $product = new Product();
        $product->setDeletedAt(new \DateTimeImmutable());

        $cartItem = new CartItem();
        $cartItem->setProduct($product);

        $this->expectException(BadRequestHttpException::class);
        $this->cartItemService->addItem($user, $cartItem);
    }

    /**
     * @return void
     */
    public function testAddItemThrowsBadRequestWhenQuantityExceedsLimit(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);
        $product = new Product();

        $cartItem = new CartItem();
        $cartItem->setProduct($product);
        $cartItem->setQuantity(100);

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartItemRepository->method('findOneBy')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->cartItemService->addItem($user, $cartItem);
    }

    /**
     * @return void
     */
    public function testAddItemThrowsBadRequestWhenTotalQuantityExceedsLimit(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);
        $product = new Product();

        $incomingCartItem = new CartItem();
        $incomingCartItem->setProduct($product);
        $incomingCartItem->setQuantity(10);

        $existingItem = new CartItem();
        $existingItem->setCart($cart);
        $existingItem->setProduct($product);
        $existingItem->setQuantity(95);

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartItemRepository->method('findOneBy')->willReturn($existingItem);

        $this->expectException(BadRequestHttpException::class);
        $this->cartItemService->addItem($user, $incomingCartItem);
    }

    /**
     * @return void
     */
    public function testUpdateQuantityThrowsBadRequestWhenQuantityExceedsLimit(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);

        $cartItem = new CartItem();
        $cartItem->setCart($cart);
        $cartItem->setQuantity(1);

        $this->expectException(BadRequestHttpException::class);
        $this->cartItemService->updateQuantity($user, $cartItem, 100);
    }

    /**
     * @return void
     */
    public function testAddItemSynchronizesCartCollection(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);
        $product = new Product();

        $cartItem = new CartItem();
        $cartItem->setProduct($product);
        $cartItem->setQuantity(1);

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartItemRepository->method('findOneBy')->willReturn(null);

        $this->cartItemService->addItem($user, $cartItem);

        $this->assertTrue($cart->getCartItems()->contains($cartItem));
    }

    /**
     * @return void
     */
    public function testRemoveItemSynchronizesCartCollection(): void
    {
        $user = new User();
        $cart = new Cart();
        $cart->setUser($user);

        $cartItem = new CartItem();
        $cart->addCartItem($cartItem);

        $this->cartItemService->removeItem($user, $cartItem);

        $this->assertFalse($cart->getCartItems()->contains($cartItem));
    }
}
