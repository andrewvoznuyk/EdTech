<?php

namespace App\Service\TwoFactor\Token;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PreAuthTokenService implements PreAuthTokenServiceInterface
{
    private const int TOKEN_EXPIRATION_SECONDS = 300;
    private const string CHALLENGE_PURPOSE = '2fa_challenge';
    private const string REVOKED_CACHE_PREFIX = '2fa_revoked_';

    /**
     * @param JWTEncoderInterface $jwtEncoder
     * @param EntityManagerInterface $entityManager
     * @param CacheItemPoolInterface $cachePool
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private EntityManagerInterface $entityManager,
        private CacheItemPoolInterface $cachePool,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param User $user
     * @return string
     * @throws RandomException
     * @throws JWTEncodeFailureException
     */
    public function createToken(User $user): string
    {
        $payload = [
            'purpose' => self::CHALLENGE_PURPOSE,
            'email' => $user->getEmail(),
            'jti' => bin2hex(random_bytes(16)),
            'exp' => time() + self::TOKEN_EXPIRATION_SECONDS,
        ];

        return $this->jwtEncoder->encode($payload);
    }

    /**
     * @param string $token
     * @return User
     * @throws UnauthorizedHttpException
     * @throws BadRequestHttpException
     * @throws InvalidArgumentException
     */
    public function validateToken(string $token): User
    {
        try {
            $payload = $this->jwtEncoder->decode($token);
        } catch (JWTDecodeFailureException) {
            throw new UnauthorizedHttpException('Bearer', $this->translator->trans('user.two_factor.invalid_token', [], 'validators'));
        }

        if (($payload['purpose'] ?? null) !== self::CHALLENGE_PURPOSE) {
            throw new UnauthorizedHttpException('Bearer', $this->translator->trans('user.two_factor.invalid_token', [], 'validators'));
        }

        $tokenHash = hash('sha256', $token);
        if ($this->cachePool->hasItem(self::REVOKED_CACHE_PREFIX . $tokenHash)) {
            throw new UnauthorizedHttpException('Bearer', $this->translator->trans('user.two_factor.invalid_token', [], 'validators'));
        }

        $email = $payload['email'] ?? $payload['username'] ?? null;
        if (!is_string($email) || empty($email)) {
            throw new UnauthorizedHttpException('Bearer', $this->translator->trans('user.two_factor.invalid_token', [], 'validators'));
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('Bearer', $this->translator->trans('user.two_factor.user_not_found', [], 'validators'));
        }

        if (!$user->isActive()) {
            throw new UnauthorizedHttpException('Bearer', $this->translator->trans('security.account_deactivated', [], 'security'));
        }

        return $user;
    }

    /**
     * @param string $token
     * @return void
     * @throws InvalidArgumentException
     */
    public function consumeToken(string $token): void
    {
        $tokenHash = hash('sha256', $token);
        $item = $this->cachePool->getItem(self::REVOKED_CACHE_PREFIX . $tokenHash);
        $item->set(true);
        $item->expiresAfter(self::TOKEN_EXPIRATION_SECONDS);
        $this->cachePool->save($item);
    }
}
