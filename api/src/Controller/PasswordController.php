<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\PasswordResetEmail;
use App\Service\User\TokenUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PasswordController extends AbstractController
{
    /**
     * @param UserRepository $userRepository
     * @param EmailSenderService $emailService
     * @param UserPasswordHasherInterface $passwordHasher
     * @param EntityManagerInterface $entityManager
     * @param TokenUserResolver $tokenUserResolver
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailSenderService $emailService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenUserResolver $tokenUserResolver,
        private readonly TranslatorInterface $translator
    ) {}

    /**
     * @param User $userDto
     * @return Response
     * @throws JWTEncodeFailureException
     * @throws TransportExceptionInterface
     */
    #[Route('/api/users/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(#[MapRequestPayload(serializationContext: ['groups' => [User::POST_FORGOT_PASSWORD_USER]], validationGroups: [User::POST_FORGOT_PASSWORD_USER])] User $userDto): Response {
        $user = $this->userRepository->findOneBy(['email' => $userDto->getEmail()]);

        if ($user) {
            $this->emailService->setStrategy(new PasswordResetEmail());
            $this->emailService->sendEmail($user);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param User $userDto
     * @return JsonResponse
     */
    #[Route('/api/users/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(#[MapRequestPayload(serializationContext: ['groups' => [User::POST_RESET_PASSWORD_USER]], validationGroups: [User::POST_RESET_PASSWORD_USER])] User $userDto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser() ?? $this->tokenUserResolver->resolve($userDto->resetToken, 'password_reset');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $userDto->getPlainPassword());
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        return new JsonResponse(['message' => $this->translator->trans('user.password.changed_successfully', [], 'validators')], Response::HTTP_OK);
    }
}
