<?php

namespace App\DTO\Common;

use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;

abstract class PaginationQueryDto
{
    #[Positive]
    public int $page = 1;

    #[Range(min: 1, max: 100)]
    public int $limit = 10;

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
