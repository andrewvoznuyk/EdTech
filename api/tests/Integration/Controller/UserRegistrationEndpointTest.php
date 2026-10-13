<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Response;

class UserRegistrationEndpointTest extends AbstractControllerIntegrationTestCase
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
    public function testRegisterSuccess(): void
    {
        $email = sprintf('new_user_%s@example.com', uniqid());

        $payload = json_encode([
            'email' => $email,
            'plainPassword' => 'securePassword123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame($email, $content['email']);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());
    }

    /**
     * @return void
     */
    public function testRegisterValidationErrorShortPassword(): void
    {
        $email = sprintf('short_pass_%s@example.com', uniqid());

        $payload = json_encode([
            'email' => $email,
            'plainPassword' => 'short',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $content);
    }

    /**
     * @return void
     */
    public function testVerifyEmailSuccess(): void
    {
        $email = sprintf('verify_%s@example.com', uniqid());
        $user = $this->createUser($email, ['ROLE_USER'], false, true, 'password123');

        $token = $this->jwtEncoder->encode([
            'email' => $email,
            'type' => \App\Enum\EmailType::VERIFICATION->value,
        ]);

        $payload = json_encode([
            'token' => $token,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/verify-email', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->entityManager->clear();
        $verifiedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($verifiedUser);
        $this->assertTrue($verifiedUser->isVerified());
    }

    /**
     * @return void
     */
    public function testResendVerificationSuccess(): void
    {
        $email = sprintf('resend_%s@example.com', uniqid());
        $this->createUser($email, ['ROLE_USER'], false, true, 'password123');

        $payload = json_encode([
            'email' => $email,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/resend-verification', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
    }
}
