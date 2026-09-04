<?php

namespace App\Enum;

enum TwoFactorMethodEnum: string
{
    case EMAIL = 'email';
    case TOTP = 'totp';
    case SMS = 'sms';
}
