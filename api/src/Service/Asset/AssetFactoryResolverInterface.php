<?php

namespace App\Service\Asset;

use App\Entity\Asset;

interface AssetFactoryResolverInterface
{
    /**
     * @param string $assetType
     * @param array<string, mixed> $options
     * @return Asset
     */
    public function createAsset(string $assetType, array $options = []): Asset;
}
