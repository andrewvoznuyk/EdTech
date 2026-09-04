<?php

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class TokenUserResolver
{
    /**
     * @param JWTEncoderInterface $jwtEncoder
     * @param UserRepository $userRepository
     * @param JWTTokenManagerInterface $jwtManager
     * @param RefreshTokenManagerInterface $refreshTokenManager
     * @param RefreshTokenGeneratorInterface $refreshTokenGenerator
     */
    public function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
    ) {}

    /**
     * @param string|null $token
     * @param string|null $expectedType
     * @return User
     * @throws BadRequestHttpException
     */
    public function resolve(?string $token, ?string $expectedType = null): User
    {
        if (!$token) {
            throw new BadRequestHttpException('Token is required for unauthorized users.');
        }

        try {
            $payload = $this->jwtEncoder->decode($token);
        } catch (JWTDecodeFailureException) {
            throw new BadRequestHttpException('Invalid or expired token.');
        }

        if ($expectedType !== null && ($payload['type'] ?? '') !== $expectedType) {
            throw new BadRequestHttpException('Invalid token type.');
        }

        $user = $this->userRepository->findOneBy(['email' => $payload['email'] ?? '']);

        if (!$user) {
            throw new BadRequestHttpException('User not found.');
        }

        return $user;
    }

    /**
     * @param User $user
     * @param $tokenTTL
     * @return JsonResponse
     */
    public function createAuthResponse(User $user, $tokenTTL): JsonResponse
    {
        $jwt = $this->jwtManager->create($user);

        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $tokenTTL);
        $this->refreshTokenManager->save($refreshToken);

        $response = new JsonResponse(['token' => $jwt]);

        $response->headers->setCookie(
            new Cookie('refresh_token', $refreshToken->getRefreshToken(), $refreshToken->getValid(), '/api/token/refresh', null, false, true, false, 'lax')
        );

        return $response;
    }
}
