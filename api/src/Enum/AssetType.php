<?php

namespace App\Enum;

use App\Enum\Trait\EnumValuesTrait;

enum AssetType: string
{
    use EnumValuesTrait;

    case SUBSCRIPTION = 'subscription';
    case WIDGET = 'widget';
}
