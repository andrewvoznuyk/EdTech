<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Event\GoogleUserAuthenticatedEvent;
use App\Service\Interface\UserRegistrationInterface;
use App\Service\TwoFactor\Token\PreAuthTokenServiceInterface;
use App\Service\TwoFactor\TwoFactorVerifierInterface;
use App\Service\User\TokenUserResolver;
use App\Validator\Exception\AccountDeletedException;
use App\Validator\GoogleTokenValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class GoogleAuthController extends AbstractController
{
    /**
     * @param UserRegistrationInterface $registrationManager
     * @param TokenUserResolver $tokenUserResolver
     * @param EventDispatcherInterface $eventDispatcher
     * @param GoogleTokenValidator $googleTokenValidator
     * @param TwoFactorVerifierInterface $twoFactorVerifier
     * @param PreAuthTokenServiceInterface $preAuthTokenService
     */
    public function __construct(
        private readonly UserRegistrationInterface $registrationManager,
        private readonly TokenUserResolver $tokenUserResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly GoogleTokenValidator $googleTokenValidator,
        private readonly TwoFactorVerifierInterface $twoFactorVerifier,
        private readonly PreAuthTokenServiceInterface $preAuthTokenService,
    ) {}

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function googleLogin(Request $request): Response
    {
        $idToken = $request->getPayload()->get('token');

        $payload = $this->googleTokenValidator->validateToken($idToken);
        $email = $payload['email'];

        if ($this->registrationManager->isAccountSoftDeleted($email)) {
            throw new AccountDeletedException($idToken);
        }

        $event = new GoogleUserAuthenticatedEvent($email, $payload);
        $this->eventDispatcher->dispatch($event);

        $user = $event->getUser();

        if ($user->has2faEnabled()) {
            return $this->createTwoFactorChallengeResponse($user);
        }

        return $this->tokenUserResolver->createAuthResponse($user, $this->getParameter('gesdinet_jwt_refresh_token.ttl'));
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/auth/google/restore', name: 'api_auth_google_restore', methods: ['POST'])]
    public function googleRestore(Request $request): Response
    {
        $idToken = $request->getPayload()->get('token');

        $payload = $this->googleTokenValidator->validateToken($idToken);
        $email = $payload['email'];

        $user = $this->registrationManager->restoreUser($email);

        if (!$user) {
            throw new BadRequestHttpException('user.google_auth.account_not_found_or_deleted');
        }

        if ($user->has2faEnabled()) {
            return $this->createTwoFactorChallengeResponse($user);
        }

        return $this->tokenUserResolver->createAuthResponse($user, $this->getParameter('gesdinet_jwt_refresh_token.ttl'));
    }

    /**
     * @param User $user
     * @return JsonResponse
     */
    private function createTwoFactorChallengeResponse(User $user): JsonResponse
    {
        $methodEnum = $user->getDefaultTwoFactorMethod();
        if (null !== $methodEnum) {
            $this->twoFactorVerifier->prepare($user, $methodEnum);
        }

        $preAuthToken = $this->preAuthTokenService->createToken($user);
        $availableMethods = $user->getActiveTwoFactorMethods();
        if (!empty($user->getBackupCodes()) && !in_array(TwoFactorMethodEnum::BACKUP_CODE->value, $availableMethods, true)) {
            $availableMethods[] = TwoFactorMethodEnum::BACKUP_CODE->value;
        }

        return new JsonResponse([
            'require_2fa' => true,
            'pre_auth_token' => $preAuthToken,
            'method' => $methodEnum?->value,
            'available_methods' => $availableMethods,
            'email' => $user->getEmail(),
        ], Response::HTTP_ACCEPTED);
    }
}
