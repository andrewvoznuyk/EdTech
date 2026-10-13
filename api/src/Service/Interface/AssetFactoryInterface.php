<?php

namespace App\Service\Interface;

use App\Entity\Asset;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.asset_factory')]
interface AssetFactoryInterface
{
    /**
     * @param string $assetType
     * @return bool
     */
    public function supports(string $assetType): bool;

    /**
     * @param array<string, mixed> $options
     * @return Asset
     */
    public function create(array $options = []): Asset;
}
