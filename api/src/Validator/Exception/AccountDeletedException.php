<?php

namespace App\Validator\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AccountDeletedException extends ConflictHttpException
{
    /**
     * @param string $restoreToken
     */
    public function __construct(
        private readonly string $restoreToken,
    ) {
        parent::__construct('user.account_deleted_restore');
    }

    /**
     * @return string
     */
    public function getRestoreToken(): string
    {
        return $this->restoreToken;
    }
}
