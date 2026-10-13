<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Asset;
use App\Entity\Cart;
use App\Entity\CompanyProfile;
use App\Entity\File;
use App\Entity\Order;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaultValues(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
        $this->assertNull($user->getEmail());
        $this->assertNull($user->getPassword());
        $this->assertNull($user->getPlainPassword());
        $this->assertNull($user->getResetToken());
        $this->assertNull($user->getCart());
        $this->assertNull($user->getUserProfile());
        $this->assertNull($user->getAuthCode());
        $this->assertSame(0, $user->getAuthCodeAttempts());
        $this->assertNull($user->getBackupCodes());
        $this->assertFalse($user->isVerified());
        $this->assertTrue($user->isActive());
        $this->assertSame('en', $user->getLocale());
        $this->assertSame([User::ROLE_USER], $user->getRoles());
        $this->assertFalse($user->has2faEnabled());
        $this->assertNull($user->getDefaultTwoFactorMethod());
        $this->assertNull($user->getDefaultTwoFactorMethodName());
        $this->assertEmpty($user->getActiveTwoFactorMethods());
        $this->assertFalse($user->hasPassword());
    }

    /**
     * @return void
     */
    public function testEmailAndUserIdentifier(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('test@example.com', $user->getUserIdentifier());
    }

    /**
     * @return void
     */
    public function testPasswordAndCredentials(): void
    {
        $user = new User();
        $user->setPassword('hashed_pass');
        $user->setPlainPassword('plain_pass');

        $this->assertTrue($user->hasPassword());
        $this->assertSame('hashed_pass', $user->getPassword());
        $this->assertSame('plain_pass', $user->getPlainPassword());

        $user->eraseCredentials();
        $this->assertNull($user->getPlainPassword());
    }

    /**
     * @return void
     */
    public function testRolesAlwaysIncludeRoleUser(): void
    {
        $user = new User();
        $user->setRoles([User::ROLE_ADMIN]);

        $this->assertContains(User::ROLE_ADMIN, $user->getRoles());
        $this->assertContains(User::ROLE_USER, $user->getRoles());
    }

    /**
     * @return void
     */
    public function testCartBidirectionalAssociation(): void
    {
        $user = new User();
        $cart = new Cart();

        $user->setCart($cart);
        $this->assertSame($cart, $user->getCart());
        $this->assertSame($user, $cart->getUser());

        $user->setCart(null);
        $this->assertNull($user->getCart());
        $this->assertNull($cart->getUser());
    }

    /**
     * @return void
     */
    public function testTwoFactorMethods(): void
    {
        $user = new User();

        $emailMethod = new UserTwoFactorMethod();
        $emailMethod->setMethod(TwoFactorMethodEnum::EMAIL);
        $emailMethod->setIsActive(true);
        $emailMethod->setIsDefault(false);

        $totpMethod = new UserTwoFactorMethod();
        $totpMethod->setMethod(TwoFactorMethodEnum::TOTP);
        $totpMethod->setIsActive(true);
        $totpMethod->setIsDefault(true);

        $user->addTwoFactorMethod($emailMethod);
        $user->addTwoFactorMethod($totpMethod);

        $this->assertTrue($user->has2faEnabled());
        $this->assertSame(TwoFactorMethodEnum::TOTP, $user->getDefaultTwoFactorMethod());
        $this->assertSame('totp', $user->getDefaultTwoFactorMethodName());
        $this->assertEqualsCanonicalizing(['email', 'totp'], $user->getActiveTwoFactorMethods());

        $user->removeTwoFactorMethod($totpMethod);
        $this->assertSame(TwoFactorMethodEnum::EMAIL, $user->getDefaultTwoFactorMethod());

        $user->removeTwoFactorMethod($emailMethod);
        $this->assertFalse($user->has2faEnabled());
        $this->assertNull($user->getDefaultTwoFactorMethod());
    }

    /**
     * @return void
     */
    public function testBackupCodesManagement(): void
    {
        $user = new User();
        $hashedCode1 = hash('sha256', 'CODE1');
        $hashedCode2 = hash('sha256', 'CODE2');

        $user->setBackupCodes([$hashedCode1, $hashedCode2]);
        $this->assertCount(2, $user->getBackupCodes());

        $removed = $user->removeBackupCode($hashedCode1);
        $this->assertTrue($removed);
        $this->assertCount(1, $user->getBackupCodes());
        $this->assertSame([$hashedCode2], $user->getBackupCodes());

        $notRemoved = $user->removeBackupCode('non_existing');
        $this->assertFalse($notRemoved);
    }

    /**
     * @return void
     */
    public function testAssetsAndFilesCollections(): void
    {
        $user = new User();

        $asset = new \App\Entity\Widget();
        $user->addAsset($asset);
        $this->assertCount(1, $user->getAssets());
        $this->assertSame($user, $asset->getUser());

        $user->removeAsset($asset);
        $this->assertCount(0, $user->getAssets());
        $this->assertNull($asset->getUser());

        $file = new File();
        $user->addFile($file);
        $this->assertCount(1, $user->getFiles());
        $this->assertSame($user, $file->getUser());

        $user->removeFile($file);
        $this->assertCount(0, $user->getFiles());
        $this->assertNull($file->getUser());
    }

    /**
     * @return void
     */
    public function testUserProfileAndCompanyProfile(): void
    {
        $user = new User();
        $userProfile = new UserProfile();
        $user->setUserProfile($userProfile);
        $this->assertSame($userProfile, $user->getUserProfile());

        $companyProfile = new CompanyProfile();
        $user->addCompanyProfile($companyProfile);
        $this->assertCount(1, $user->getCompanyProfile());
        $this->assertSame($user, $companyProfile->getUser());

        $user->removeCompanyProfile($companyProfile);
        $this->assertCount(0, $user->getCompanyProfile());
        $this->assertNull($companyProfile->getUser());
    }

    /**
     * @return void
     */
    public function testTimestampableAndVerification(): void
    {
        $user = new User();
        $now = new DateTimeImmutable();

        $user->setIsVerified(true);
        $user->setIsActive(false);
        $user->setLocale('uk');
        $user->setLastVerificationEmailSentAt($now);
        $user->setAuthCodeExpiresAt($now);

        $this->assertTrue($user->isVerified());
        $this->assertFalse($user->isActive());
        $this->assertSame('uk', $user->getLocale());
        $this->assertSame($now, $user->getLastVerificationEmailSentAt());
        $this->assertSame($now, $user->getAuthCodeExpiresAt());
    }
}
