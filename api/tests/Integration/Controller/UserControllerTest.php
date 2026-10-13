<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends AbstractControllerIntegrationTestCase
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
    public function testRestoreRequestSoftDeletedUser(): void
    {
        $email = sprintf('restore_deleted_%s@example.com', uniqid());
        $user = $this->createUser($email);
        $user->setDeletedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $payload = json_encode(['email' => $email], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/restore-request', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testRestoreRequestActiveUser(): void
    {
        $email = sprintf('restore_active_%s@example.com', uniqid());
        $user = $this->createUser($email);

        $payload = json_encode(['email' => $email], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/restore-request', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testRestoreConfirmValidToken(): void
    {
        $email = sprintf('restore_confirm_%s@example.com', uniqid());
        $user = $this->createUser($email);
        $user->setDeletedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $token = $this->jwtEncoder->encode([
            'email' => $email,
            'type' => 'restore_account',
        ]);

        $payload = json_encode(['token' => $token], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/restore-confirm', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
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

    /**
     * @return void
     */
    public function testRestoreConfirmInvalidToken(): void
    {
        $payload = json_encode(['token' => 'invalid.restore.token'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/restore-confirm', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }
}
