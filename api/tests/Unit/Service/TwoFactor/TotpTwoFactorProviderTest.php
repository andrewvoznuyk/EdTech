<?php

namespace App\Tests\Unit\Service\TwoFactor;

use App\Entity\User;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use App\Service\Security\Encryption\SecretCipherInterface;
use App\Service\TwoFactor\Provider\TotpTwoFactorProvider;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TotpTwoFactorProviderTest extends TestCase
{
    /**
     * @var SecretCipherInterface&MockObject
     */
    private SecretCipherInterface&MockObject $secretCipher;

    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var TotpTwoFactorProvider
     */
    private TotpTwoFactorProvider $provider;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->secretCipher = $this->createMock(SecretCipherInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->provider = new TotpTwoFactorProvider(
            $this->secretCipher,
            $this->entityManager
        );
    }

    /**
     * @return void
     */
    public function testGetName(): void
    {
        $this->assertSame(TwoFactorMethodEnum::TOTP, $this->provider->getName());
    }

    /**
     * @return void
     */
    public function testGenerateSecret(): void
    {
        $secret = $this->provider->generateSecret();
        $this->assertNotEmpty($secret);
        $this->assertIsString($secret);
    }

    /**
     * @return void
     */
    public function testGenerateQrCodeDataUri(): void
    {
        $secret = $this->provider->generateSecret();
        $qrCodeDataUri = $this->provider->generateQrCodeDataUri($secret, 'test@example.com');

        $this->assertStringStartsWith('data:image/png;base64,', $qrCodeDataUri);
    }

    /**
     * @return void
     */
    public function testVerifyCode(): void
    {
        $secret = $this->provider->generateSecret();
        $totp = TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        $this->assertTrue($this->provider->verifyCode($secret, $validCode));
        $this->assertFalse($this->provider->verifyCode($secret, '000000'));
        $this->assertFalse($this->provider->verifyCode($secret, ''));
    }

    /**
     * @return void
     */
    public function testValidateAuthenticationSuccess(): void
    {
        $secret = $this->provider->generateSecret();
        $totp = TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        $user = new User();
        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::TOTP);
        $method->setIsActive(true);
        $method->setSecret('encrypted_secret');
        $user->addTwoFactorMethod($method);

        $this->secretCipher->expects($this->once())
            ->method('decrypt')
            ->with('encrypted_secret')
            ->willReturn($secret);

        $this->entityManager->expects($this->once())->method('flush');

        $isValid = $this->provider->validateAuthentication($user, $validCode);

        $this->assertTrue($isValid);
        $this->assertNotNull($method->getLastUsedSlice());
    }

    /**
     * @return void
     */
    public function testValidateAuthenticationReplayAttackPrevented(): void
    {
        $secret = $this->provider->generateSecret();
        $totp = TOTP::createFromSecret($secret);
        $validCode = $totp->now();
        $currentSlice = intdiv(time(), $totp->getPeriod());

        $user = new User();
        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::TOTP);
        $method->setIsActive(true);
        $method->setSecret('encrypted_secret');
        $method->setLastUsedSlice($currentSlice); // Already used in this time slice!
        $user->addTwoFactorMethod($method);

        $this->secretCipher->method('decrypt')->willReturn($secret);
        $this->entityManager->expects($this->never())->method('flush');

        $isValid = $this->provider->validateAuthentication($user, $validCode);

        $this->assertFalse($isValid);
    }

    /**
     * @return void
     */
    public function testValidateAuthenticationFailsIfNoActiveTotpMethod(): void
    {
        $user = new User();
        $isValid = $this->provider->validateAuthentication($user, '123456');

        $this->assertFalse($isValid);
    }
}
