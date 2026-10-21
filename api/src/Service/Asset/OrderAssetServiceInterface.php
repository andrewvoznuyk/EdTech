<?php

namespace App\Service\Asset;

use App\Entity\Asset;
use App\Entity\Order;

interface OrderAssetServiceInterface
{
    /**
     * @param Order $order
     * @return array<Asset>
     */
    public function createAssetsForOrder(Order $order): array;
}
