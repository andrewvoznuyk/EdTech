<?php

namespace App\Enum;

enum FileStatus: string
{
    case PENDING = 'pending';
    case UPLOADED = 'uploaded';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';
}