<?php

namespace App\Service\EntityService\Product;

use App\DTO\Common\PaginatedResult;
use App\DTO\Product\Request\ProductQueryDto;

interface ProductServiceInterface
{
    /**
     * @param ProductQueryDto $filterDto
     * @return PaginatedResult
     */
    public function getCollection(ProductQueryDto $filterDto): PaginatedResult;
}
