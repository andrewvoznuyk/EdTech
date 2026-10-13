<?php

namespace App\Service\EntityService\Product;

use App\DTO\Common\PaginatedResult;
use App\DTO\Product\Request\ProductQueryDto;
use App\Repository\ProductRepository;

/**
 * @implements ProductServiceInterface
 */
final readonly class ProductService implements ProductServiceInterface
{
    /**
     * @param ProductRepository $productRepository
     */
    public function __construct(
        private ProductRepository $productRepository,
    ) {
    }

    /**
     * @param ProductQueryDto $filterDto
     * @return PaginatedResult
     */
    public function getCollection(ProductQueryDto $filterDto): PaginatedResult
    {
        return PaginatedResult::fromPaginator(
            $this->productRepository->getFilteredPaginator($filterDto),
            $filterDto,
        );
    }
}
