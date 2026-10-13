<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\Token\PreAuthTokenServiceInterface;
use App\Service\TwoFactor\TwoFactorVerifierInterface;
use App\Service\User\UserTwoFactorSetupServiceInterface;
use OTPHP\TOTP;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @var PreAuthTokenServiceInterface
     */
    private PreAuthTokenServiceInterface $preAuthTokenService;

    /**
     * @var TwoFactorVerifierInterface
     */
    private TwoFactorVerifierInterface $twoFactorVerifier;

    /**
     * @var UserTwoFactorSetupServiceInterface
     */
    private UserTwoFactorSetupServiceInterface $setupService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->preAuthTokenService = self::getContainer()->get(PreAuthTokenServiceInterface::class);
        $this->twoFactorVerifier = self::getContainer()->get(TwoFactorVerifierInterface::class);
        $this->setupService = self::getContainer()->get(UserTwoFactorSetupServiceInterface::class);
    }

    /**
     * @return void
     */
    public function testCheckMissingToken(): void
    {
        $payload = json_encode([
            'code' => '123456',
            'method' => 'totp',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/2fa/check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testResendMissingToken(): void
    {
        $payload = json_encode([
            'method' => 'email',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/2fa/resend', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testSetupTotpUnauthorized(): void
    {
        $this->client->request('POST', '/api/users/two-factor/totp/setup');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testSetupTotpSuccess(): void
    {
        $user = $this->createUser(sprintf('totp_setup_%s@example.com', uniqid()));
        $headers = $this->getAuthHeaders($user);

        $this->client->request('POST', '/api/users/two-factor/totp/setup', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('secret', $content);
        $this->assertArrayHasKey('qr_code', $content);
    }

    /**
     * @return void
     */
    public function testConfirmTotpMissingCode(): void
    {
        $user = $this->createUser(sprintf('totp_missing_%s@example.com', uniqid()));
        $headers = $this->getAuthHeaders($user);

        $payload = json_encode([], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/two-factor/totp/confirm', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testConfirmTotpSuccess(): void
    {
        $user = $this->createUser(sprintf('totp_confirm_%s@example.com', uniqid()));
        $headers = $this->getAuthHeaders($user);

        // 1. Setup TOTP
        $this->client->request('POST', '/api/users/two-factor/totp/setup', [], [], $headers);
        $setupContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $secret = $setupContent['secret'];

        // 2. Generate valid TOTP code
        $totp = TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        // 3. Confirm TOTP
        $payload = json_encode(['code' => $validCode], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/users/two-factor/totp/confirm', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('backup_codes', $content);
        $this->assertCount(8, $content['backup_codes']);
    }

    /**
     * @return void
     */
    public function testSetDefaultMethodInvalid(): void
    {
        $user = $this->createUser(sprintf('default_invalid_%s@example.com', uniqid()));
        $headers = $this->getAuthHeaders($user);

        $payload = json_encode(['method' => 'invalid_method'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/two-factor/set-default', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testSetDefaultMethodSuccess(): void
    {
        $user = $this->createUser(sprintf('default_method_%s@example.com', uniqid()));
        $headers = $this->getAuthHeaders($user);

        // Setup & confirm TOTP first
        $this->client->request('POST', '/api/users/two-factor/totp/setup', [], [], $headers);
        $setupContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $totp = TOTP::createFromSecret($setupContent['secret']);
        $payload = json_encode(['code' => $totp->now()], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/users/two-factor/totp/confirm', [], [], $headers, $payload);

        // Set default method
        $defaultPayload = json_encode(['method' => 'totp'], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/users/two-factor/set-default', [], [], $headers, $defaultPayload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGenerateBackupCodesMissingPassword(): void
    {
        $user = $this->createUser(sprintf('backup_no_pass_%s@example.com', uniqid()), ['ROLE_USER'], true, true, 'password123');
        $headers = $this->getAuthHeaders($user);

        $payload = json_encode([], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/two-factor/generate-backup-codes', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGenerateBackupCodesSuccess(): void
    {
        $user = $this->createUser(sprintf('backup_success_%s@example.com', uniqid()), ['ROLE_USER'], true, true, 'password123');
        $headers = $this->getAuthHeaders($user);

        // Enable TOTP first so user has 2FA enabled
        $this->client->request('POST', '/api/users/two-factor/totp/setup', [], [], $headers);
        $setupContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $totp = TOTP::createFromSecret($setupContent['secret']);
        $setupPayload = json_encode(['code' => $totp->now()], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/users/two-factor/totp/confirm', [], [], $headers, $setupPayload);

        $payload = json_encode(['password' => 'password123'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/two-factor/generate-backup-codes', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('backup_codes', $content);
        $this->assertCount(8, $content['backup_codes']);
    }

    /**
     * @return void
     */
    public function testDisableTwoFactorMissingPassword(): void
    {
        $user = $this->createUser(sprintf('disable_no_pass_%s@example.com', uniqid()), ['ROLE_USER'], true, true, 'password123');
        $headers = $this->getAuthHeaders($user);

        $payload = json_encode([], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/users/two-factor/disable', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testDisableTwoFactorSuccess(): void
    {
        $user = $this->createUser(sprintf('disable_success_%s@example.com', uniqid()), ['ROLE_USER'], true, true, 'password123');
        $headers = $this->getAuthHeaders($user);

        // Setup & confirm TOTP
        $this->client->request('POST', '/api/users/two-factor/totp/setup', [], [], $headers);
        $setupContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $totp = TOTP::createFromSecret($setupContent['secret']);
        $payload = json_encode(['code' => $totp->now()], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/users/two-factor/totp/confirm', [], [], $headers, $payload);

        // Disable 2FA
        $disablePayload = json_encode(['password' => 'password123'], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/users/two-factor/disable', [], [], $headers, $disablePayload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testCheckSuccessWithBackupCode(): void
    {
        $user = $this->createUser(sprintf('check_backup_%s@example.com', uniqid()), ['ROLE_USER'], true, true, 'password123');
        $headers = $this->getAuthHeaders($user);

        // Setup and confirm TOTP to enable 2FA and get backup codes
        $this->client->request('POST', '/api/users/two-factor/totp/setup', [], [], $headers);
        $setupContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $totp = TOTP::createFromSecret($setupContent['secret']);
        $confirmPayload = json_encode(['code' => $totp->now()], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/users/two-factor/totp/confirm', [], [], $headers, $confirmPayload);

        $confirmContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $chosenCode = $confirmContent['backup_codes'][0];

        $preAuthToken = $this->preAuthTokenService->createToken($user);

        $payload = json_encode([
            'pre_auth_token' => $preAuthToken,
            'code' => $chosenCode,
            'method' => TwoFactorMethodEnum::BACKUP_CODE->value,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/2fa/check', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('token', $content);
    }
}
