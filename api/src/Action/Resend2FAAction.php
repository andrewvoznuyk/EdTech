<?php

namespace App\Action;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\TwoFactorManager;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

readonly class Resend2FAAction implements ProcessorInterface
{
    /**
     * @param UserRepository $userRepository
     * @param TwoFactorManager $twoFactorManager
     * @param RateLimiterFactory $twoFactorResendLimiter
     */
    public function __construct(
        private UserRepository $userRepository,
        private TwoFactorManager $twoFactorManager,
        private RateLimiterFactory $twoFactorResendLimiter
    ) {}

    /**
     * @param mixed $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return mixed
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User) {
            return $data;
        }
        $limiter = $this->twoFactorResendLimiter->create($data->getEmail());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }
        $user = $this->userRepository->findOneBy(['email' => $data->getEmail()]);
        if (!$user) {
            return null;
        }
        $this->twoFactorManager->setProviderByEnum(TwoFactorMethodEnum::EMAIL);
        $this->twoFactorManager->prepare($user);
        return null;
    }
}
