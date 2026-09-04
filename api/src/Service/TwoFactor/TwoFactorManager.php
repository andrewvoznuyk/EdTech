<?php

namespace App\Service\TwoFactor;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use InvalidArgumentException;

class TwoFactorManager
{
    /**
     * @var array<string, TwoFactorProviderInterface>
     */
    private array $providers = [];

    /**
     * @var TwoFactorProviderInterface|null
     */
    private ?TwoFactorProviderInterface $activeProvider = null;

    /**
     * @param iterable $twoFactorProviders
     */
    public function __construct(
        #[AutowireIterator('app.two_factor_provider')] iterable $twoFactorProviders
    ) {
        foreach ($twoFactorProviders as $provider) {
            $this->providers[$provider->getName()->value] = $provider;
        }
    }

    /**
     * @param TwoFactorMethodEnum $method
     * @return void
     */
    public function setProviderByEnum(TwoFactorMethodEnum $method): void
    {
        if (!isset($this->providers[$method->value])) {
            throw new InvalidArgumentException(sprintf('Unknown 2FA method: %s', $method->value));
        }
        $this->activeProvider = $this->providers[$method->value];
    }

    /**
     * @param User $user
     * @return void
     */
    public function prepare(User $user): void
    {
        if (null === $this->activeProvider) {
            throw new InvalidArgumentException('No 2FA provider selected. Call setProviderByEnum() first.');
        }
        $this->activeProvider->prepareAuthentication($user);
    }

    /**
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function validate(User $user, string $code): bool
    {
        if (null === $this->activeProvider) {
            throw new InvalidArgumentException('No 2FA provider selected. Call setProviderByEnum() first.');
        }
        return $this->activeProvider->validateAuthentication($user, $code);
    }

    /**
     * @return TwoFactorProviderInterface|null
     */
    public function getActiveProvider(): ?TwoFactorProviderInterface
    {
        return $this->activeProvider;
    }
}
