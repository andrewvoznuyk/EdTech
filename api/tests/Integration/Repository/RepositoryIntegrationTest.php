<?php

namespace App\Tests\Integration\Repository;

use App\DTO\Cart\Request\CartQueryDto;
use App\DTO\Product\Request\ProductQueryDto;
use App\Entity\Cart;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\AssetType;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RepositoryIntegrationTest extends KernelTestCase
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ProductRepository
     */
    private ProductRepository $productRepository;

    /**
     * @var CartRepository
     */
    private CartRepository $cartRepository;

    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->productRepository = $this->entityManager->getRepository(Product::class);
        $this->cartRepository = $this->entityManager->getRepository(Cart::class);
        $this->userRepository = $this->entityManager->getRepository(User::class);

        $this->entityManager->beginTransaction();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testProductRepositoryFilters(): void
    {
        $product1 = new Product();
        $product1->setTitle('Unique Plan Alpha');
        $product1->setPrice('100.00');
        $product1->setAssetType(AssetType::WIDGET->value);
        $this->entityManager->persist($product1);

        $product2 = new Product();
        $product2->setTitle('Beta Service');
        $product2->setPrice('50.00');
        $product2->setAssetType(AssetType::WIDGET->value);
        $this->entityManager->persist($product2);

        $this->entityManager->flush();

        // Filter by title
        $titleFilterDto = new ProductQueryDto();
        $titleFilterDto->title = 'Alpha';
        $paginatorTitle = $this->productRepository->getFilteredPaginator($titleFilterDto);
        $this->assertSame(1, count($paginatorTitle));

        // Filter by minPrice
        $priceFilterDto = new ProductQueryDto();
        $priceFilterDto->minPrice = '75.00';
        $paginatorPrice = $this->productRepository->getFilteredPaginator($priceFilterDto);
        $this->assertSame(1, count($paginatorPrice));

        // Filter by assetType
        $typeFilterDto = new ProductQueryDto();
        $typeFilterDto->assetType = AssetType::WIDGET->value;
        $paginatorType = $this->productRepository->getFilteredPaginator($typeFilterDto);
        $this->assertGreaterThanOrEqual(2, count($paginatorType));
    }

    /**
     * @return void
     */
    public function testCartRepositoryFilters(): void
    {
        $user = new User();
        $user->setEmail('cart_repo_test@example.com');
        $this->entityManager->persist($user);

        $cart = new Cart();
        $cart->setUser($user);
        $this->entityManager->persist($cart);

        $this->entityManager->flush();

        $cartFilterDto = new CartQueryDto();
        $cartFilterDto->order = Order::Descending;

        $paginator = $this->cartRepository->getFilteredPaginator($cartFilterDto, $user);
        $this->assertSame(1, count($paginator));
    }

    /**
     * @return void
     */
    public function testUserRepositoryUpgradePassword(): void
    {
        $user = new User();
        $user->setEmail('upgrade_pass_test@example.com');
        $user->setPassword('initial_hash');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->userRepository->upgradePassword($user, 'new_upgraded_hash');

        $refreshedUser = $this->userRepository->findOneBy(['email' => 'upgrade_pass_test@example.com']);
        $this->assertNotNull($refreshedUser);
        $this->assertSame('new_upgraded_hash', $refreshedUser->getPassword());
    }
}
