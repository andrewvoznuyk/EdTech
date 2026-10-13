<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    /**
     * @var UserChecker
     */
    private UserChecker $userChecker;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->userChecker = new UserChecker();
    }

    /**
     * @return void
     */
    public function testCheckPreAuthPassesForActiveUser(): void
    {
        $user = new User();
        $user->setIsActive(true);

        $this->userChecker->checkPreAuth($user);
        $this->assertTrue($user->isActive());
    }

    /**
     * @return void
     */
    public function testCheckPreAuthThrowsForInactiveUser(): void
    {
        $user = new User();
        $user->setIsActive(false);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('security.account_deactivated');

        $this->userChecker->checkPreAuth($user);
    }

    /**
     * @return void
     */
    public function testCheckPreAuthIgnoresNonAppUser(): void
    {
        $otherUser = $this->createMock(UserInterface::class);

        $this->userChecker->checkPreAuth($otherUser);
        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function testCheckPostAuthPassesForVerifiedUser(): void
    {
        $user = new User();
        $user->setIsVerified(true);

        $this->userChecker->checkPostAuth($user);
        $this->assertTrue($user->isVerified());
    }

    /**
     * @return void
     */
    public function testCheckPostAuthThrowsForUnverifiedUser(): void
    {
        $user = new User();
        $user->setIsVerified(false);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('security.email_not_verified');

        $this->userChecker->checkPostAuth($user);
    }

    /**
     * @return void
     */
    public function testCheckPostAuthIgnoresNonAppUser(): void
    {
        $otherUser = $this->createMock(UserInterface::class);

        $this->userChecker->checkPostAuth($otherUser);
        $this->assertTrue(true);
    }
}
