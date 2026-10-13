<?php

namespace App\DTO\Common;

use Symfony\Component\Serializer\Attribute\Groups;

readonly class Pagination
{
    /**
     * @var int
     */
    #[Groups(PaginatedResult::GET_COLLECTION_PAGINATION)]
    public int $totalPages;

    /**
     * @var bool
     */
    #[Groups(PaginatedResult::GET_COLLECTION_PAGINATION)]
    public bool $hasNextPage;

    /**
     * @var bool
     */
    #[Groups(PaginatedResult::GET_COLLECTION_PAGINATION)]
    public bool $hasPrevPage;

    /**
     * @param int $page
     * @param int $limit
     * @param int $total
     */
    public function __construct(
        #[Groups(PaginatedResult::GET_COLLECTION_PAGINATION)]
        public int $page,
        #[Groups(PaginatedResult::GET_COLLECTION_PAGINATION)]
        public int $limit,
        #[Groups(PaginatedResult::GET_COLLECTION_PAGINATION)]
        public int $total,
    ) {
        $this->totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;
        $this->hasNextPage = $page < $this->totalPages;
        $this->hasPrevPage = $page > 1;
    }
}
