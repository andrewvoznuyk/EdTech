<?php

namespace App\Enum;

enum EmailType: string
{
    case PASSWORD_RESET = 'password_reset';
    case RESTORE_ACCOUNT = 'restore_account';
    case VERIFICATION = 'verification';
    case RESEND_2FA = 'resend_2fa';
}
