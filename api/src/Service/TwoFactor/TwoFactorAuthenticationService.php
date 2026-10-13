<?php

namespace App\Service\TwoFactor;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\Token\PreAuthTokenServiceInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TwoFactorAuthenticationService implements TwoFactorAuthenticationServiceInterface
{
    /**
     * @param PreAuthTokenServiceInterface $preAuthTokenService
     * @param RateLimiterFactory $twoFactorAuthLimiter
     * @param RateLimiterFactory $twoFactorResendLimiter
     * @param TwoFactorVerifierInterface $twoFactorVerifier
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private PreAuthTokenServiceInterface $preAuthTokenService,
        private RateLimiterFactory $twoFactorAuthLimiter,
        private RateLimiterFactory $twoFactorResendLimiter,
        private TwoFactorVerifierInterface $twoFactorVerifier,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $preAuthToken
     * @param string $code
     * @param string $method
     * @return User
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function authenticate(string $preAuthToken, string $code, string $method): User
    {
        if (!$code) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.code_required', [], 'validators'));
        }

        if (!$preAuthToken) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.token_required', [], 'validators'));
        }

        $user = $this->preAuthTokenService->validateToken($preAuthToken);

        $methodEnum = TwoFactorMethodEnum::tryFrom($method);
        if (!$methodEnum) {
            $methodEnum = $user->getDefaultTwoFactorMethod();
        }
        if (!$methodEnum) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_method', [], 'validators'));
        }

        $limiter = $this->twoFactorAuthLimiter->create((string) $user->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        if (!$user->has2faEnabled()) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.method_not_enabled', [], 'validators'));
        }

        if ($methodEnum !== TwoFactorMethodEnum::BACKUP_CODE && !in_array($method, $user->getActiveTwoFactorMethods(), true)) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.method_not_enabled', [], 'validators'));
        }

        $cleanCode = str_replace([' ', '-'], '', trim($code));
        $isValid = $this->twoFactorVerifier->validate($user, $cleanCode, $methodEnum);
        if (!$isValid) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_code', [], 'validators'));
        }

        $this->preAuthTokenService->consumeToken($preAuthToken);
        $this->twoFactorAuthLimiter->create((string) $user->getEmail())->reset();
        $this->twoFactorResendLimiter->create((string) $user->getEmail())->reset();

        return $user;
    }

    /**
     * @param string $preAuthToken
     * @param string $method
     * @return void
     * @throws BadRequestHttpException
     * @throws TooManyRequestsHttpException
     */
    public function resend(string $preAuthToken, string $method): void
    {
        if (!$preAuthToken) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.token_required', [], 'validators'));
        }

        $user = $this->preAuthTokenService->validateToken($preAuthToken);

        $methodEnum = TwoFactorMethodEnum::tryFrom($method);
        if ($methodEnum !== TwoFactorMethodEnum::EMAIL) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_method', [], 'validators'));
        }

        if (!in_array(TwoFactorMethodEnum::EMAIL->value, $user->getActiveTwoFactorMethods(), true)) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.method_not_enabled', [], 'validators'));
        }

        $limiter = $this->twoFactorResendLimiter->create((string) $user->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $this->twoFactorVerifier->prepare($user, TwoFactorMethodEnum::EMAIL);
    }
}
