<?php

namespace App\DTO\Common;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Serializer\Attribute\Groups;

readonly class PaginatedResult
{
    public const string GET_COLLECTION_PAGINATION = "get:collection:pagination";

    /**
     * @var Pagination
     */
    #[Groups(self::GET_COLLECTION_PAGINATION)]
    public Pagination $pagination;

    /**
     * @param iterable<object> $items
     * @param int $page
     * @param int $limit
     * @param int $total
     */
    public function __construct(
        #[Groups(self::GET_COLLECTION_PAGINATION)]
        public iterable $items,
        int $page,
        int $limit,
        int $total,
    ) {
        $this->pagination = new Pagination($page, $limit, $total);
    }

    /**
     * @template T of object
     * @param Paginator<T> $paginator
     * @param PaginationQueryDto $queryDto
     * @return self
     */
    public static function fromPaginator(Paginator $paginator, PaginationQueryDto $queryDto): self
    {
        return new self(
            items: iterator_to_array($paginator, false),
            page: $queryDto->page,
            limit: $queryDto->limit,
            total: count($paginator),
        );
    }
}
