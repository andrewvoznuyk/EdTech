<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use App\Validator\GoogleTokenValidator;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @var GoogleTokenValidator
     */
    private GoogleTokenValidator $googleTokenValidatorMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->googleTokenValidatorMock = $this->createMock(GoogleTokenValidator::class);
        self::getContainer()->set(GoogleTokenValidator::class, $this->googleTokenValidatorMock);
    }

    /**
     * @return void
     */
    public function testGoogleLoginSuccessWithout2fa(): void
    {
        $email = sprintf('google_user_%s@example.com', uniqid());

        $this->googleTokenValidatorMock
            ->expects($this->once())
            ->method('validateToken')
            ->with('valid_token')
            ->willReturn([
                'email' => $email,
                'given_name' => 'Google',
                'family_name' => 'User',
            ]);

        $payload = json_encode(['token' => 'valid_token'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/auth/google', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('token', $content);
    }

    /**
     * @return void
     */
    public function testGoogleLoginSoftDeletedUser(): void
    {
        $email = sprintf('google_deleted_%s@example.com', uniqid());
        $user = $this->createUser($email);
        $user->setDeletedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $this->googleTokenValidatorMock
            ->expects($this->once())
            ->method('validateToken')
            ->with('deleted_token')
            ->willReturn([
                'email' => $email,
            ]);

        $payload = json_encode(['token' => 'deleted_token'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/auth/google', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('restore_token', $content);
        $this->assertSame('deleted_token', $content['restore_token']);
    }

    /**
     * @return void
     */
    public function testGoogleLoginWith2faEnabled(): void
    {
        $email = sprintf('google_2fa_%s@example.com', uniqid());
        $user = $this->createUser($email);

        $method = new UserTwoFactorMethod();
        $method->setUser($user);
        $method->setMethod(TwoFactorMethodEnum::TOTP);
        $method->setIsActive(true);
        $method->setIsDefault(true);
        $method->setSecret('encrypted_secret');
        $user->addTwoFactorMethod($method);
        $this->entityManager->persist($method);
        $this->entityManager->flush();

        $this->googleTokenValidatorMock
            ->expects($this->once())
            ->method('validateToken')
            ->with('2fa_token')
            ->willReturn([
                'email' => $email,
            ]);

        $payload = json_encode(['token' => '2fa_token'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/auth/google', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertTrue($content['require_2fa']);
        $this->assertArrayHasKey('pre_auth_token', $content);
    }

    /**
     * @return void
     */
    public function testGoogleRestoreNonExistentUser(): void
    {
        $email = sprintf('google_not_found_%s@example.com', uniqid());

        $this->googleTokenValidatorMock
            ->expects($this->once())
            ->method('validateToken')
            ->with('not_found_token')
            ->willReturn([
                'email' => $email,
            ]);

        $payload = json_encode(['token' => 'not_found_token'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/auth/google/restore', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGoogleRestoreSoftDeletedUser(): void
    {
        $email = sprintf('google_restore_%s@example.com', uniqid());
        $user = $this->createUser($email);
        $user->setDeletedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $this->googleTokenValidatorMock
            ->expects($this->once())
            ->method('validateToken')
            ->with('restore_token')
            ->willReturn([
                'email' => $email,
            ]);

        $payload = json_encode(['token' => 'restore_token'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/auth/google/restore', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('token', $content);

        // Verify user is restored in database
        $this->entityManager->clear();
        $this->entityManager->getFilters()->disable('softdeleteable');
        $restoredUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($restoredUser);
        $this->assertNull($restoredUser->getDeletedAt());
        $this->entityManager->getFilters()->enable('softdeleteable');
    }
}
