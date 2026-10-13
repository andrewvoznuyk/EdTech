<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\VerificationEmail;
use App\Service\Interface\UserRegistrationInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class UserRegistrationProcessor implements ProcessorInterface
{
    /**
     * @param UserRegistrationInterface $registrationManager
     * @param EmailSenderService $emailSenderService
     */
    public function __construct(
        private UserRegistrationInterface $registrationManager,
        private EmailSenderService $emailSenderService,
    ) {
    }

    /**
     * @param mixed $data
     * @param Operation $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     * @return mixed
     * @throws JWTEncodeFailureException
     * @throws TransportExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User) {
            return $data;
        }

        $user = $this->registrationManager->registerUser(user: $data);

        $this->emailSenderService->setStrategy(new VerificationEmail());
        $this->emailSenderService->sendEmail($user);

        return $user;
    }
}
