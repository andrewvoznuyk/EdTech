<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\RestoreAccountEmail;
use App\Service\User\TokenUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    /**
     * @param EmailSenderService $emailService
     * @param EntityManagerInterface $entityManager
     * @param TokenUserResolver $tokenUserResolver
     */
    public function __construct(
        private readonly EmailSenderService $emailService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenUserResolver $tokenUserResolver
    ) {}

    /**
     * @param User $userDto
     * @return Response
     * @throws JWTEncodeFailureException
     * @throws TransportExceptionInterface
     */
    #[Route('/api/users/restore-request', name: 'api_restore_account_request', methods: ['POST'])]
    public function restoreRequest(#[MapRequestPayload(serializationContext: ['groups' => [User::POST_RESTORE_REQUEST]], validationGroups: [User::POST_RESTORE_REQUEST])] User $userDto): Response {

        $this->entityManager->getFilters()->disable('softdeleteable');

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userDto->getEmail()]);

        if ($user && $user->getDeletedAt() !== null) {
            $this->emailService->setStrategy(new RestoreAccountEmail());
            $this->emailService->sendEmail($user);
        }

        $this->entityManager->getFilters()->enable('softdeleteable');

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param User $userDto
     * @return JsonResponse
     */
    #[Route('/api/users/restore-confirm', name: 'api_restore_account_confirm', methods: ['POST'])]
    public function restoreConfirm(#[MapRequestPayload(serializationContext: ['groups' => [User::POST_RESTORE_CONFIRM]], validationGroups: [User::POST_RESTORE_CONFIRM])] User $userDto): JsonResponse
    {
        $this->entityManager->getFilters()->disable('softdeleteable');

        $user = $this->tokenUserResolver->resolve($userDto->token, 'restore_account');

        if ($user->getDeletedAt() !== null) {
            $user->setDeletedAt(null);
            $this->entityManager->flush();
        }

        $this->entityManager->getFilters()->enable('softdeleteable');

        return $this->tokenUserResolver->createAuthResponse($user, $this->getParameter('gesdinet_jwt_refresh_token.ttl'));
    }
}
