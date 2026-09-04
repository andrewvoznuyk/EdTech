<?php

namespace App\Action;

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\VerificationEmail;
use App\Service\Interface\UserRegistrationInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class RegisterUserAction implements ProcessorInterface
{
    public function __construct(
        private UserRegistrationInterface $registrationManager,
        private EmailSenderService $emailSenderService,
    ) {}

    /**
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
