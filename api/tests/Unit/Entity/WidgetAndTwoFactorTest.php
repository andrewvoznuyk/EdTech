<?php

namespace App\Tests\Unit\Entity;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Entity\UserTwoFactorMethod;
use App\Entity\Widget;
use App\Enum\TwoFactorMethodEnum;
use DateTime;
use PHPUnit\Framework\TestCase;

class WidgetAndTwoFactorTest extends TestCase
{
    /**
     * @return void
     */
    public function testWidget(): void
    {
        $widget = new Widget();

        $this->assertSame('widget', $widget->getAssetType());
        $this->assertNull($widget->getRate());
        $this->assertNull($widget->getView());

        $widget->setRate(5);
        $widget->setView(100);

        $this->assertSame(5, $widget->getRate());
        $this->assertSame(100, $widget->getView());
    }

    /**
     * @return void
     */
    public function testUserProfile(): void
    {
        $profile = new UserProfile();
        $user = new User();

        $this->assertNull($profile->getUser());
        $this->assertNull($profile->getFirstName());
        $this->assertNull($profile->getLastName());
        $this->assertNull($profile->getPhone());

        $profile->setUser($user);
        $profile->setFirstName('Ivan');
        $profile->setLastName('Franko');
        $profile->setPhone('+380509876543');

        $this->assertSame($user, $profile->getUser());
        $this->assertSame('Ivan', $profile->getFirstName());
        $this->assertSame('Franko', $profile->getLastName());
        $this->assertSame('+380509876543', $profile->getPhone());
    }

    /**
     * @return void
     */
    public function testUserTwoFactorMethod(): void
    {
        $method = new UserTwoFactorMethod();
        $user = new User();

        $method->setUser($user);
        $method->setMethod(TwoFactorMethodEnum::TOTP);
        $method->setIsActive(true);
        $method->setIsDefault(true);
        $method->setSecret('ENCRYPTED_SECRET');
        $method->setPendingSecret('PENDING_SECRET');
        $method->setLastUsedSlice(123456);

        $this->assertSame($user, $method->getUser());
        $this->assertSame(TwoFactorMethodEnum::TOTP, $method->getMethod());
        $this->assertTrue($method->isActive());
        $this->assertTrue($method->isDefault());
        $this->assertSame('ENCRYPTED_SECRET', $method->getSecret());
        $this->assertSame('PENDING_SECRET', $method->getPendingSecret());
        $this->assertSame(123456, $method->getLastUsedSlice());
    }

    /**
     * @return void
     */
    public function testRefreshToken(): void
    {
        $token = new RefreshToken();
        $validDate = new DateTime('+1 month');

        $token->setRefreshToken('token_string_123');
        $token->setUsername('user@example.com');
        $token->setValid($validDate);

        $this->assertSame('token_string_123', $token->getRefreshToken());
        $this->assertSame('user@example.com', $token->getUsername());
        $this->assertSame($validDate, $token->getValid());
        $this->assertTrue($token->isValid());
    }
}
