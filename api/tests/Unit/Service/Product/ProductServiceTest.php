<?php

namespace App\Tests\Unit\Service\Product;

use App\DTO\Common\PaginatedResult;
use App\DTO\Product\Request\ProductQueryDto;
use App\Repository\ProductRepository;
use App\Service\EntityService\Product\ProductService;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends TestCase
{
    /**
     * @var ProductRepository&MockObject
     */
    private ProductRepository&MockObject $productRepository;

    /**
     * @var ProductService
     */
    private ProductService $productService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->productService = new ProductService($this->productRepository);
    }

    /**
     * @return void
     */
    public function testGetCollection(): void
    {
        $filterDto = new ProductQueryDto();
        $paginator = $this->createMock(Paginator::class);

        $paginator->method('getIterator')->willReturn(new \ArrayIterator([]));
        $paginator->method('count')->willReturn(5);

        $this->productRepository->expects($this->once())
            ->method('getFilteredPaginator')
            ->with($filterDto)
            ->willReturn($paginator);

        $result = $this->productService->getCollection($filterDto);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertSame(5, $result->pagination->total);
    }
}
