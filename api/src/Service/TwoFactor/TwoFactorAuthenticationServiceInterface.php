<?php

namespace App\Service\TwoFactor;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

interface TwoFactorAuthenticationServiceInterface
{
    /**
     * @param string $preAuthToken
     * @param string $code
     * @param string $method
     * @return User
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function authenticate(string $preAuthToken, string $code, string $method): User;

    /**
     * @param string $preAuthToken
     * @param string $method
     * @return void
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function resend(string $preAuthToken, string $method): void;
}
