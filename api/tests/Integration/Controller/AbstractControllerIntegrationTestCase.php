<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractControllerIntegrationTestCase extends WebTestCase
{
    /**
     * @var KernelBrowser
     */
    protected KernelBrowser $client;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var JWTTokenManagerInterface
     */
    protected JWTTokenManagerInterface $jwtTokenManager;

    /**
     * @var UserPasswordHasherInterface
     */
    protected UserPasswordHasherInterface $passwordHasher;

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->jwtTokenManager = self::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }

        parent::tearDown();
    }

    /**
     * @param string $email
     * @param array<int, string> $roles
     * @param bool $isVerified
     * @param bool $isActive
     * @param string|null $plainPassword
     * @return User
     */
    protected function createUser(
        string $email,
        array $roles = ['ROLE_USER'],
        bool $isVerified = true,
        bool $isActive = true,
        ?string $plainPassword = 'password123'
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setIsVerified($isVerified);
        $user->setIsActive($isActive);

        if (null !== $plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param string $email
     * @return User
     */
    protected function createAdminUser(string $email = 'admin@example.com'): User
    {
        return $this->createUser($email, ['ROLE_ADMIN', 'ROLE_USER']);
    }

    /**
     * @param User $user
     * @return string
     */
    protected function generateJwtToken(User $user): string
    {
        return $this->jwtTokenManager->create($user);
    }

    /**
     * @param User $user
     * @return array<string, string>
     */
    protected function getAuthHeaders(User $user): array
    {
        return [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->generateJwtToken($user)),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }
}
