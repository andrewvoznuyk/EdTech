<?php

namespace App\DTO\Product\Request;

use App\DTO\Common\PaginationQueryDto;
use App\Enum\AssetType;
use Doctrine\Common\Collections\Order;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ProductQueryDto extends PaginationQueryDto
{
    #[Length(max: 255)]
    public ?string $title = null;

    #[Choice(callback: [AssetType::class, 'values'])]
    public ?string $assetType = null;

    #[PositiveOrZero]
    public ?string $minPrice = null;

    /**
     * @var Order
     */
    public Order $order = Order::Descending;
}
