<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\VerificationEmail;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ResendVerificationProcessor implements ProcessorInterface
{
    /**
     * @param UserRepository $userRepository
     * @param EmailSenderService $emailSenderService
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private UserRepository $userRepository,
        private EmailSenderService $emailSenderService,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
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
     * @throws TooManyRequestsHttpException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User || empty($data->getEmail())) {
            return $data;
        }

        $user = $this->userRepository->findOneBy(['email' => $data->getEmail()]);

        if ($user && !$user->isVerified()) {
            $lastSentAt = $user->getLastVerificationEmailSentAt();
            $now = new DateTimeImmutable();

            if ($lastSentAt !== null && ($now->getTimestamp() - $lastSentAt->getTimestamp()) < 60) {
                throw new TooManyRequestsHttpException(60, $this->translator->trans('error.rate_limit', [], 'email'));
            }

            $user->setLastVerificationEmailSentAt($now);
            $this->entityManager->flush();

            $this->emailSenderService->setStrategy(new VerificationEmail());
            $this->emailSenderService->sendEmail($user);
        }

        return null;
    }
}
