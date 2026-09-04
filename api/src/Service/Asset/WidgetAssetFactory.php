<?php

namespace App\Service\Asset;

use App\Entity\Asset;
use App\Entity\Widget;
use App\Service\Interface\AssetFactoryInterface;

class WidgetAssetFactory implements AssetFactoryInterface
{
    /**
     * @param string $assetType
     * @return bool
     */
    public function supports(string $assetType): bool
    {
        return $assetType === 'widget';
    }

    /**
     * @return Asset
     */
    public function create(): Asset
    {
        $widget = new Widget();
        $widget->setRate(5)->setView(155);
        return $widget;
    }
}
