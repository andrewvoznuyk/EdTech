<?php

namespace App\Service\Asset;

use App\Entity\Asset;
use App\Entity\Widget;
use App\Enum\AssetType;
use App\Service\Interface\AssetFactoryInterface;

/**
 * @implements AssetFactoryInterface
 */
class WidgetAssetFactory implements AssetFactoryInterface
{
    /**
     * @param string $assetType
     * @return bool
     */
    public function supports(string $assetType): bool
    {
        return $assetType === 'widget' || $assetType === AssetType::WIDGET->value;
    }

    /**
     * @param array<string, mixed> $options
     * @return Asset
     */
    public function create(array $options = []): Asset
    {
        $widget = new Widget();

        if (isset($options['rate'])) {
            $widget->setRate((int) $options['rate']);
        }

        if (isset($options['view'])) {
            $widget->setView((int) $options['view']);
        }

        return $widget;
    }
}
