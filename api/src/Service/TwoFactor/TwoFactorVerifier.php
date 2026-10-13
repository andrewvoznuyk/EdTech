<?php

namespace App\Service\TwoFactor;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\Provider\TwoFactorProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class TwoFactorVerifier implements TwoFactorVerifierInterface
{
    /**
     * @var array<string, TwoFactorProviderInterface>
     */
    private array $providers;

    /**
     * @param iterable<TwoFactorProviderInterface> $twoFactorProviders
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        #[AutowireIterator('app.two_factor_provider')] iterable $twoFactorProviders,
        private EntityManagerInterface $entityManager,
    ) {
        $providers = [];
        foreach ($twoFactorProviders as $provider) {
            $providers[$provider->getName()->value] = $provider;
        }
        $this->providers = $providers;
    }

    /**
     * @param User $user
     * @param TwoFactorMethodEnum $method
     * @return void
     * @throws InvalidArgumentException
     */
    public function prepare(User $user, TwoFactorMethodEnum $method): void
    {
        if ($method === TwoFactorMethodEnum::BACKUP_CODE) {
            return;
        }

        $provider = $this->getProvider($method);
        $provider->prepareAuthentication($user);
    }

    /**
     * @param User $user
     * @param string $code
     * @param TwoFactorMethodEnum $method
     * @return bool
     */
    public function validate(User $user, string $code, TwoFactorMethodEnum $method): bool
    {
        if ($method === TwoFactorMethodEnum::BACKUP_CODE) {
            return $this->validateBackupCode($user, $code);
        }

        $provider = $this->getProvider($method);

        return $provider->validateAuthentication($user, $code);
    }

    /**
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function validateBackupCode(User $user, string $code): bool
    {
        $backupCodes = $user->getBackupCodes();
        if (empty($backupCodes)) {
            return false;
        }

        $normalizedCode = strtoupper(str_replace(['-', ' '], '', trim($code)));
        $hashedNormalized = hash('sha256', $normalizedCode);

        $formattedCode = strlen($normalizedCode) === 8
            ? sprintf('%s-%s', substr($normalizedCode, 0, 4), substr($normalizedCode, 4, 4))
            : trim($code);
        $hashedFormatted = hash('sha256', $formattedCode);

        $removed = $user->removeBackupCode($hashedNormalized) || $user->removeBackupCode($hashedFormatted);
        if ($removed) {
            $this->entityManager->flush();

            return true;
        }

        return false;
    }

    /**
     * @param User $user
     * @return array<string>
     * @throws RandomException
     */
    public function generateBackupCodes(User $user): array
    {
        $plainCodes = [];
        $hashedCodes = [];

        while (count($plainCodes) < 8) {
            $part1 = strtoupper(bin2hex(random_bytes(2)));
            $part2 = strtoupper(bin2hex(random_bytes(2)));
            $plainFormatted = sprintf('%s-%s', $part1, $part2);
            $normalized = sprintf('%s%s', $part1, $part2);

            if (!in_array($plainFormatted, $plainCodes, true)) {
                $plainCodes[] = $plainFormatted;
                $hashedCodes[] = hash('sha256', $normalized);
            }
        }

        $user->setBackupCodes($hashedCodes);
        $this->entityManager->flush();

        return $plainCodes;
    }

    /**
     * @param TwoFactorMethodEnum $method
     * @return TwoFactorProviderInterface
     * @throws InvalidArgumentException
     */
    private function getProvider(TwoFactorMethodEnum $method): TwoFactorProviderInterface
    {
        if (!isset($this->providers[$method->value])) {
            throw new InvalidArgumentException(sprintf('Unknown 2FA provider: %s', $method->value));
        }

        return $this->providers[$method->value];
    }
}
