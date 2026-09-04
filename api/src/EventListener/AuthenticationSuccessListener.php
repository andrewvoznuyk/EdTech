<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\TwoFactor\TwoFactorManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success', method: 'onAuthenticationSuccess', priority: 100)]
readonly class AuthenticationSuccessListener
{
    /**
     * @param RequestStack $requestStack
     * @param TranslatorInterface $translator
     * @param TwoFactorManager $twoFactorManager
     */
    public function __construct(
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private TwoFactorManager $twoFactorManager
    ) {}

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
        $this->twoFactorManager->setProviderByEnum($methodEnum);
        try {
            $this->twoFactorManager->prepare($user);
        } catch (TooManyRequestsHttpException $e) {
        }

        $event->setData([
            'require_2fa' => true,
            'method' => $methodEnum->value,
            'available_methods' => $user->getActiveTwoFactorMethods(),
            'email' => $user->getEmail(),
            'message' => $this->translator->trans('response.2fa_required', [], 'security')
        ]);

        $response = $event->getResponse();
        $response->setStatusCode(202);

        $response->headers->clearCookie('refresh_token', '/api/token/refresh', null, false, true, 'lax');

        $event->stopPropagation();
    }
}
