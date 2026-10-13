<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Response;

class PasswordControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @var JWTEncoderInterface
     */
    private JWTEncoderInterface $jwtEncoder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtEncoder = self::getContainer()->get(JWTEncoderInterface::class);
    }

    /**
     * @return void
     */
    public function testForgotPasswordExistingUser(): void
    {
        $email = sprintf('forgot_%s@example.com', uniqid());
        $user = $this->createUser($email);

        $payload = json_encode([
            'email' => $user->getEmail(),
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testForgotPasswordRateLimit(): void
    {
        $email = sprintf('rate_limit_%s@example.com', uniqid());
        $user = $this->createUser($email);

        $payload = json_encode(['email' => $user->getEmail()], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/api/users/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testForgotPasswordNonExistingUser(): void
    {
        $payload = json_encode([
            'email' => 'nonexistent_user@example.com',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/forgot-password', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testResetPasswordValidToken(): void
    {
        $user = $this->createUser('reset_password_user@example.com', ['ROLE_USER'], true, true, 'oldPassword123');

        $resetToken = $this->jwtEncoder->encode([
            'email' => $user->getEmail(),
            'type' => 'password_reset',
        ]);

        $payload = json_encode([
            'resetToken' => $resetToken,
            'plainPassword' => 'newSecretPassword123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->entityManager->clear();
        $refreshedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'reset_password_user@example.com']);
        $this->assertNotNull($refreshedUser);
        $this->assertTrue($this->passwordHasher->isPasswordValid($refreshedUser, 'newSecretPassword123'));
    }

    /**
     * @return void
     */
    public function testResetPasswordInvalidToken(): void
    {
        $payload = json_encode([
            'resetToken' => 'invalid.jwt.token',
            'plainPassword' => 'newSecretPassword123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testResetPasswordValidationShortPassword(): void
    {
        $payload = json_encode([
            'resetToken' => 'some.token.here',
            'plainPassword' => 'short',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('errors', $content);
    }
}
