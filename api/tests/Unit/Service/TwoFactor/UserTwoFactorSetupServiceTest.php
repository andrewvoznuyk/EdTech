<?php

namespace App\Tests\Unit\Service\TwoFactor;

use App\Entity\User;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use App\Service\Security\Encryption\SecretCipherInterface;
use App\Service\TwoFactor\Provider\TotpTwoFactorProviderInterface;
use App\Service\TwoFactor\TwoFactorVerifierInterface;
use App\Service\User\UserTwoFactorSetupService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserTwoFactorSetupServiceTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var TwoFactorVerifierInterface&MockObject
     */
    private TwoFactorVerifierInterface&MockObject $twoFactorVerifier;

    /**
     * @var TotpTwoFactorProviderInterface&MockObject
     */
    private TotpTwoFactorProviderInterface&MockObject $totpProvider;

    /**
     * @var SecretCipherInterface&MockObject
     */
    private SecretCipherInterface&MockObject $secretCipher;

    /**
     * @var UserPasswordHasherInterface&MockObject
     */
    private UserPasswordHasherInterface&MockObject $passwordHasher;

    /**
     * @var TranslatorInterface&MockObject
     */
    private TranslatorInterface&MockObject $translator;

    /**
     * @var RateLimiterFactory
     */
    private RateLimiterFactory $authLimiterFactory;

    /**
     * @var RateLimiterFactory
     */
    private RateLimiterFactory $resendLimiterFactory;

    /**
     * @var UserTwoFactorSetupService
     */
    private UserTwoFactorSetupService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->twoFactorVerifier = $this->createMock(TwoFactorVerifierInterface::class);
        $this->totpProvider = $this->createMock(TotpTwoFactorProviderInterface::class);
        $this->secretCipher = $this->createMock(SecretCipherInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->translator->method('trans')->willReturnArgument(0);

        $this->authLimiterFactory = new RateLimiterFactory(
            ['id' => 'auth_setup', 'policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 minute'],
            new InMemoryStorage()
        );

        $this->resendLimiterFactory = new RateLimiterFactory(
            ['id' => 'resend_setup', 'policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 minute'],
            new InMemoryStorage()
        );

        $this->service = new UserTwoFactorSetupService(
            $this->entityManager,
            $this->twoFactorVerifier,
            $this->totpProvider,
            $this->secretCipher,
            $this->passwordHasher,
            $this->authLimiterFactory,
            $this->resendLimiterFactory,
            $this->translator
        );
    }

    /**
     * @return void
     */
    public function testEnableEmailTwoFactor(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->twoFactorVerifier->expects($this->once())
            ->method('prepare')
            ->with($user, TwoFactorMethodEnum::EMAIL);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(UserTwoFactorMethod::class));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->enableEmailTwoFactor($user);

        $this->assertCount(1, $user->getTwoFactorMethods());
    }

    /**
     * @return void
     */
    public function testConfirmEmailTwoFactor(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->twoFactorVerifier->expects($this->once())
            ->method('validate')
            ->with($user, '123456', TwoFactorMethodEnum::EMAIL)
            ->willReturn(true);

        $this->twoFactorVerifier->expects($this->once())
            ->method('generateBackupCodes')
            ->with($user)
            ->willReturn(['AAAA-BBBB']);

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->confirmEmailTwoFactor($user, '123456');

        $this->assertSame(['AAAA-BBBB'], $result['backup_codes']);
        $this->assertTrue($user->has2faEnabled());
    }

    /**
     * @return void
     */
    public function testSetupTotpTwoFactor(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->totpProvider->expects($this->once())->method('generateSecret')->willReturn('BASE32SECRET');
        $this->totpProvider->expects($this->once())
            ->method('generateQrCodeDataUri')
            ->with('BASE32SECRET', 'user@example.com')
            ->willReturn('data:image/png;base64,123');

        $this->secretCipher->expects($this->once())
            ->method('encrypt')
            ->with('BASE32SECRET')
            ->willReturn('encrypted_secret');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->setupTotpTwoFactor($user);

        $this->assertSame('BASE32SECRET', $result['secret']);
        $this->assertSame('data:image/png;base64,123', $result['qr_code']);
    }

    /**
     * @return void
     */
    public function testConfirmTotpTwoFactor(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::TOTP);
        $method->setPendingSecret('enc_sec');
        $user->addTwoFactorMethod($method);

        $this->secretCipher->expects($this->once())->method('decrypt')->with('enc_sec')->willReturn('plain_sec');
        $this->totpProvider->expects($this->once())->method('verifyCode')->with('plain_sec', '654321')->willReturn(true);
        $this->twoFactorVerifier->expects($this->once())->method('generateBackupCodes')->willReturn(['BC1', 'BC2']);

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->confirmTotpTwoFactor($user, '654321');

        $this->assertTrue($method->isActive());
        $this->assertSame('enc_sec', $method->getSecret());
        $this->assertNull($method->getPendingSecret());
        $this->assertSame(['BC1', 'BC2'], $result['backup_codes']);
    }

    /**
     * @return void
     */
    public function testDisableTwoFactorAll(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword('hash');

        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::EMAIL);
        $method->setIsActive(true);
        $user->addTwoFactorMethod($method);
        $user->setBackupCodes(['code1']);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'secretpass')
            ->willReturn(true);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($method);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->disableTwoFactor($user, 'secretpass', null);

        $this->assertFalse($user->has2faEnabled());
        $this->assertNull($user->getBackupCodes());
    }

    /**
     * @return void
     */
    public function testSetDefaultTwoFactorMethod(): void
    {
        $user = new User();

        $method1 = new UserTwoFactorMethod();
        $method1->setMethod(TwoFactorMethodEnum::EMAIL);
        $method1->setIsActive(true);
        $method1->setIsDefault(true);

        $method2 = new UserTwoFactorMethod();
        $method2->setMethod(TwoFactorMethodEnum::TOTP);
        $method2->setIsActive(true);
        $method2->setIsDefault(false);

        $user->addTwoFactorMethod($method1);
        $user->addTwoFactorMethod($method2);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->setDefaultTwoFactorMethod($user, TwoFactorMethodEnum::TOTP);

        $this->assertFalse($method1->isDefault());
        $this->assertTrue($method2->isDefault());
    }

    /**
     * @return void
     */
    public function testGenerateBackupCodesRequiresValidPassword(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword('hashed');

        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::EMAIL);
        $method->setIsActive(true);
        $user->addTwoFactorMethod($method);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'wrong_pass')
            ->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->service->generateBackupCodes($user, 'wrong_pass');
    }
}
