<?php

namespace App\Tests\Unit\Service\TwoFactor;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\Provider\TwoFactorProviderInterface;
use App\Service\TwoFactor\TwoFactorVerifier;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TwoFactorVerifierTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var TwoFactorProviderInterface&MockObject
     */
    private TwoFactorProviderInterface&MockObject $emailProvider;

    /**
     * @var TwoFactorVerifier
     */
    private TwoFactorVerifier $verifier;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emailProvider = $this->createMock(TwoFactorProviderInterface::class);
        $this->emailProvider->method('getName')->willReturn(TwoFactorMethodEnum::EMAIL);

        $this->verifier = new TwoFactorVerifier(
            [$this->emailProvider],
            $this->entityManager
        );
    }

    /**
     * @return void
     */
    public function testPrepareEmailMethod(): void
    {
        $user = new User();
        $this->emailProvider->expects($this->once())
            ->method('prepareAuthentication')
            ->with($user);

        $this->verifier->prepare($user, TwoFactorMethodEnum::EMAIL);
    }

    /**
     * @return void
     */
    public function testPrepareBackupCodeNoop(): void
    {
        $user = new User();
        $this->emailProvider->expects($this->never())->method('prepareAuthentication');

        $this->verifier->prepare($user, TwoFactorMethodEnum::BACKUP_CODE);
    }

    /**
     * @return void
     */
    public function testPrepareThrowsOnUnknownProvider(): void
    {
        $user = new User();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown 2FA provider: totp');

        $this->verifier->prepare($user, TwoFactorMethodEnum::TOTP);
    }

    /**
     * @return void
     */
    public function testValidateViaProvider(): void
    {
        $user = new User();
        $this->emailProvider->expects($this->once())
            ->method('validateAuthentication')
            ->with($user, '123456')
            ->willReturn(true);

        $isValid = $this->verifier->validate($user, '123456', TwoFactorMethodEnum::EMAIL);
        $this->assertTrue($isValid);
    }

    /**
     * @return void
     */
    public function testGenerateAndValidateBackupCodes(): void
    {
        $user = new User();
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $codes = $this->verifier->generateBackupCodes($user);

        $this->assertCount(8, $codes);
        $this->assertCount(8, $user->getBackupCodes());

        // Validate the first backup code
        $codeToUse = $codes[0];
        $isValid = $this->verifier->validate($user, $codeToUse, TwoFactorMethodEnum::BACKUP_CODE);
        $this->assertTrue($isValid);
        $this->assertCount(7, $user->getBackupCodes());

        // Using the same backup code again should fail
        $isInvalidSecondTime = $this->verifier->validate($user, $codeToUse, TwoFactorMethodEnum::BACKUP_CODE);
        $this->assertFalse($isInvalidSecondTime);
    }

    /**
     * @return void
     */
    public function testValidateBackupCodeReturnsFalseWhenEmpty(): void
    {
        $user = new User();
        $user->setBackupCodes([]);

        $isValid = $this->verifier->validateBackupCode($user, 'ABCD-EFGH');
        $this->assertFalse($isValid);
    }
}
