<?php

namespace App\Tests\Unit\Service\Cart;

use App\DTO\Cart\Request\CartQueryDto;
use App\DTO\Common\PaginatedResult;
use App\Entity\Cart;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Service\EntityService\Cart\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CartServiceTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var CartRepository&MockObject
     */
    private CartRepository&MockObject $cartRepository;

    /**
     * @var CartService
     */
    private CartService $cartService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cartRepository = $this->createMock(CartRepository::class);
        $this->cartService = new CartService(
            $this->entityManager,
            $this->cartRepository
        );
    }

    /**
     * @return void
     */
    public function testGetCartReturnsExistingCart(): void
    {
        $user = new User();
        $existingCart = new Cart();
        $user->setCart($existingCart);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $cart = $this->cartService->getCart($user);

        $this->assertSame($existingCart, $cart);
    }

    /**
     * @return void
     */
    public function testGetCartCreatesNewCartWhenNull(): void
    {
        $user = new User();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Cart::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $cart = $this->cartService->getCart($user);

        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertSame($user, $cart->getUser());
        $this->assertSame($cart, $user->getCart());
    }

    /**
     * @return void
     */
    public function testCreateMethodDirectly(): void
    {
        $user = new User();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Cart $cart) use ($user) {
                return $cart->getUser() === $user;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $cart = $this->cartService->create($user);

        $this->assertSame($user, $cart->getUser());
    }

    /**
     * @return void
     */
    public function testGetCollection(): void
    {
        $filterDto = new CartQueryDto();
        $paginator = $this->createMock(Paginator::class);

        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));
        $paginator->method('count')->willReturn(0);

        $this->cartRepository->expects($this->once())
            ->method('getFilteredPaginator')
            ->with($filterDto)
            ->willReturn($paginator);

        $result = $this->cartService->getCollection($filterDto);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertSame(0, $result->pagination->total);
    }
}
