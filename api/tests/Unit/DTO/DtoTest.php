<?php

namespace App\Tests\Unit\DTO;

use App\DTO\Cart\Request\CartQueryDto;
use App\DTO\Common\PaginatedResult;
use App\DTO\Common\Pagination;
use App\DTO\Product\Request\ProductQueryDto;
use Doctrine\Common\Collections\Order;
use PHPUnit\Framework\TestCase;

class DtoTest extends TestCase
{
    /**
     * @return void
     */
    public function testPaginationCalculations(): void
    {
        $pagination = new Pagination(page: 1, limit: 10, total: 25);
        $this->assertSame(3, $pagination->totalPages);
        $this->assertTrue($pagination->hasNextPage);
        $this->assertFalse($pagination->hasPrevPage);

        $middlePagination = new Pagination(page: 2, limit: 10, total: 25);
        $this->assertTrue($middlePagination->hasNextPage);
        $this->assertTrue($middlePagination->hasPrevPage);

        $lastPagination = new Pagination(page: 3, limit: 10, total: 25);
        $this->assertFalse($lastPagination->hasNextPage);
        $this->assertTrue($lastPagination->hasPrevPage);

        $emptyPagination = new Pagination(page: 1, limit: 0, total: 0);
        $this->assertSame(0, $emptyPagination->totalPages);
    }

    /**
     * @return void
     */
    public function testPaginatedResult(): void
    {
        $items = ['item1', 'item2'];
        $result = new PaginatedResult(items: $items, page: 1, limit: 10, total: 2);

        $this->assertSame($items, $result->items);
        $this->assertSame(1, $result->pagination->totalPages);
    }

    /**
     * @return void
     */
    public function testPaginationQueryDtoOffsets(): void
    {
        $cartQuery = new CartQueryDto();
        $this->assertSame(1, $cartQuery->page);
        $this->assertSame(10, $cartQuery->limit);
        $this->assertSame(0, $cartQuery->getOffset());
        $this->assertSame(Order::Descending, $cartQuery->order);

        $cartQuery->page = 3;
        $cartQuery->limit = 20;
        $this->assertSame(40, $cartQuery->getOffset());

        $productQuery = new ProductQueryDto();
        $this->assertSame(1, $productQuery->page);
        $this->assertSame(10, $productQuery->limit);
        $this->assertNull($productQuery->title);
        $this->assertNull($productQuery->assetType);
        $this->assertNull($productQuery->minPrice);
        $this->assertSame(Order::Descending, $productQuery->order);

        $productQuery->title = 'Test';
        $productQuery->assetType = 'widget';
        $productQuery->minPrice = '100.00';
        $productQuery->order = Order::Ascending;

        $this->assertSame('Test', $productQuery->title);
        $this->assertSame('widget', $productQuery->assetType);
        $this->assertSame('100.00', $productQuery->minPrice);
        $this->assertSame(Order::Ascending, $productQuery->order);
    }
}
