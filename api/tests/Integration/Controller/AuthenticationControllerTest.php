<?php

namespace App\Tests\Integration\Controller;

use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     * @throws JsonException
     */
    public function testLoginSuccessWithout2fa(): void
    {
        $email = sprintf('login_%s@example.com', uniqid());
        $user = $this->createUser($email, ['ROLE_USER'], true, true, 'password123');

        $payload = json_encode([
            'email' => $email,
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/login-check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('token', $content);

        // Verify refresh token cookie is set
        $cookies = $this->client->getResponse()->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'refresh_token') {
                $found = true;
                $this->assertGreaterThan(time(), $cookie->getExpiresTime());
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testLoginWrongPassword(): void
    {
        $email = sprintf('wrong_pass_%s@example.com', uniqid());
        $this->createUser($email, ['ROLE_USER'], true, true, 'correctPassword');

        $payload = json_encode([
            'email' => $email,
            'password' => 'wrongPassword',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/login-check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testLoginUnverifiedUser(): void
    {
        $email = sprintf('unverified_%s@example.com', uniqid());
        $this->createUser($email, ['ROLE_USER'], false, true, 'password123');

        $payload = json_encode([
            'email' => $email,
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/login-check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testLoginInactiveUser(): void
    {
        $email = sprintf('inactive_%s@example.com', uniqid());
        $this->createUser($email, ['ROLE_USER'], true, false, 'password123');

        $payload = json_encode([
            'email' => $email,
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/login-check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testLoginWith2faEnabled(): void
    {
        $email = sprintf('login_2fa_%s@example.com', uniqid());
        $user = $this->createUser($email, ['ROLE_USER'], true, true, 'password123');

        $method = new UserTwoFactorMethod();
        $method->setUser($user);
        $method->setMethod(TwoFactorMethodEnum::TOTP);
        $method->setIsActive(true);
        $method->setIsDefault(true);
        $method->setSecret('encrypted_secret');
        $user->addTwoFactorMethod($method);
        $this->entityManager->persist($method);
        $this->entityManager->flush();

        $payload = json_encode([
            'email' => $email,
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/login-check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertTrue($content['require_2fa']);
        $this->assertArrayHasKey('pre_auth_token', $content);
        $this->assertSame('totp', $content['method']);
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testRefreshTokenSuccess(): void
    {
        $email = sprintf('refresh_test_%s@example.com', uniqid());
        $this->createUser($email, ['ROLE_USER'], true, true, 'password123');

        $payload = json_encode([
            'email' => $email,
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR);

        // 1. Login to receive refresh token cookie
        $this->client->request('POST', '/api/login-check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // 2. Call /api/token/refresh with cookies preserved
        $this->client->request('POST', '/api/token/refresh');
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('token', $content);
    }
}
