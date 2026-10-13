<?php

namespace App\Enum;

use App\Enum\Trait\EnumValuesTrait;

enum EmailType: string
{
    use EnumValuesTrait;

    case PASSWORD_RESET = 'password_reset';
    case RESTORE_ACCOUNT = 'restore_account';
    case VERIFICATION = 'verification';
    case RESEND_2FA = 'resend_2fa';
}
