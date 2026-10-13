<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\Token\PreAuthTokenServiceInterface;
use App\Service\TwoFactor\TwoFactorVerifierInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success', method: 'onAuthenticationSuccess', priority: 100)]
readonly class AuthenticationSuccessListener
{
    /**
     * @param RequestStack $requestStack
     * @param TwoFactorVerifierInterface $twoFactorVerifier
     * @param PreAuthTokenServiceInterface $preAuthTokenService
     */
    public function __construct(
        private RequestStack $requestStack,
        private TwoFactorVerifierInterface $twoFactorVerifier,
        private PreAuthTokenServiceInterface $preAuthTokenService,
    ) {
    }

    /**
     * @param AuthenticationSuccessEvent $event
     * @return void
     */
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || $request->getPathInfo() !== '/api/login-check') {
            return;
        }

        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        if (!$user->has2faEnabled()) {
            return;
        }

        $methodEnum = $user->getDefaultTwoFactorMethod();
        if (null !== $methodEnum) {
            $this->twoFactorVerifier->prepare($user, $methodEnum);
        }

        $preAuthToken = $this->preAuthTokenService->createToken($user);
        $availableMethods = $user->getActiveTwoFactorMethods();
        if (!empty($user->getBackupCodes()) && !in_array(TwoFactorMethodEnum::BACKUP_CODE->value, $availableMethods, true)) {
            $availableMethods[] = TwoFactorMethodEnum::BACKUP_CODE->value;
        }

        $event->setData([
            'require_2fa' => true,
            'pre_auth_token' => $preAuthToken,
            'method' => $methodEnum?->value,
            'available_methods' => $availableMethods,
            'email' => $user->getEmail(),
        ]);

        $response = $event->getResponse();
        $response->setStatusCode(Response::HTTP_ACCEPTED);
        $response->headers->clearCookie('refresh_token', '/api/token/refresh', null, false, true, 'lax');

        $event->stopPropagation();
    }
}
