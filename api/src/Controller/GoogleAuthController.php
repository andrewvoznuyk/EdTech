<?php

namespace App\Controller;

use App\Event\GoogleUserAuthenticatedEvent;
use App\Service\Interface\UserRegistrationInterface;
use App\Service\User\TokenUserResolver;
use App\Validator\Exception\AccountDeletedException;
use App\Validator\GoogleTokenValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
     */
    public function __construct(
        private readonly UserRegistrationInterface $registrationManager,
        private readonly TokenUserResolver $tokenUserResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly GoogleTokenValidator $googleTokenValidator,
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

        return $this->tokenUserResolver->createAuthResponse($user, $this->getParameter('gesdinet_jwt_refresh_token.ttl'));
    }
}
