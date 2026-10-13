<?php

namespace App\Enum;

use App\Enum\Trait\EnumValuesTrait;

enum TwoFactorMethodEnum: string
{
    use EnumValuesTrait;

    case EMAIL = 'email';
    case TOTP = 'totp';
    case SMS = 'sms';
    case BACKUP_CODE = 'backup_code';
}
