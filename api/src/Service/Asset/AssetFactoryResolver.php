<?php

namespace App\Service\Asset;

use App\Entity\Asset;
use App\Service\Interface\AssetFactoryInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * @implements AssetFactoryResolverInterface
 */
class AssetFactoryResolver implements AssetFactoryResolverInterface
{
    /**
     * @param iterable<AssetFactoryInterface> $factories
     */
    public function __construct(
        #[AutowireIterator('app.asset_factory')]
        private readonly iterable $factories
    ) {
    }

    /**
     * @param string $assetType
     * @param array<string, mixed> $options
     * @return Asset
     * @throws InvalidArgumentException
     */
    public function createAsset(string $assetType, array $options = []): Asset
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($assetType)) {
                return $factory->create($options);
            }
        }

        if ($assetType === 'widget') {
            return (new WidgetAssetFactory())->create($options);
        }

        throw new InvalidArgumentException(sprintf('Unsupported asset type "%s".', $assetType));
    }
}
