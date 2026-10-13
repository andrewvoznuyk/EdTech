<?php

namespace App\Tests\Unit\Entity;

use App\Entity\CompanyProfile;
use App\Entity\File;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CompanyProfileTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaultValues(): void
    {
        $profile = new CompanyProfile();

        $this->assertNull($profile->getId());
        $this->assertNull($profile->getUser());
        $this->assertNull($profile->getCompanyName());
        $this->assertNull($profile->getEdrpou());
        $this->assertNull($profile->getRnokpp());
        $this->assertNull($profile->getPhone());
        $this->assertNull($profile->getPosition());
        $this->assertNull($profile->getActualAddress());
        $this->assertNull($profile->getRegistrationAddress());
        $this->assertNull($profile->getLogo());
        $this->assertNull($profile->getWallpaper());
    }

    /**
     * @return void
     */
    public function testSettersAndGetters(): void
    {
        $profile = new CompanyProfile();
        $user = new User();
        $logo = new File();
        $wallpaper = new File();

        $profile->setUser($user);
        $profile->setCompanyName('Acme Corp');
        $profile->setEdrpou('12345678');
        $profile->setRnokpp(null);
        $profile->setPhone('+380501234567');
        $profile->setPosition('CEO');
        $profile->setActualAddress('Kyiv, Khreshchatyk 1');
        $profile->setRegistrationAddress('Kyiv, Khreshchatyk 1');
        $profile->setLogo($logo);
        $profile->setWallpaper($wallpaper);

        $this->assertSame($user, $profile->getUser());
        $this->assertSame('Acme Corp', $profile->getCompanyName());
        $this->assertSame('12345678', $profile->getEdrpou());
        $this->assertNull($profile->getRnokpp());
        $this->assertSame('+380501234567', $profile->getPhone());
        $this->assertSame('CEO', $profile->getPosition());
        $this->assertSame('Kyiv, Khreshchatyk 1', $profile->getActualAddress());
        $this->assertSame('Kyiv, Khreshchatyk 1', $profile->getRegistrationAddress());
        $this->assertSame($logo, $profile->getLogo());
        $this->assertSame($wallpaper, $profile->getWallpaper());
    }
}
