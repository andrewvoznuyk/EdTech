<?php

namespace App\Enum;

use App\Enum\Trait\EnumValuesTrait;

enum FileStatus: string
{
    use EnumValuesTrait;

    case PENDING = 'pending';
    case UPLOADED = 'uploaded';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';
}
