<?php

namespace App\Service\Interface;

use App\Entity\Asset;

interface AssetFactoryInterface
{
    /**
     * @param string $assetType
     * @return bool
     */
    public function supports(string $assetType): bool;

    /**
     * @return Asset
     */
    public function create(): Asset;
}
