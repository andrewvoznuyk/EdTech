<?php

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\UserRegistrationManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserRegistrationManagerTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var UserRepository&MockObject
     */
    private UserRepository&MockObject $userRepository;

    /**
     * @var UserPasswordHasherInterface&MockObject
     */
    private UserPasswordHasherInterface&MockObject $passwordHasher;

    /**
     * @var TranslatorInterface&MockObject
     */
    private TranslatorInterface&MockObject $translator;

    /**
     * @var FilterCollection&MockObject
     */
    private FilterCollection&MockObject $filterCollection;

    /**
     * @var UserRegistrationManager
     */
    private UserRegistrationManager $manager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->filterCollection = $this->createMock(FilterCollection::class);

        $this->entityManager->method('getFilters')->willReturn($this->filterCollection);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->manager = new UserRegistrationManager(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasher,
            $this->translator
        );
    }

    /**
     * @return void
     */
    public function testRegisterUserSuccess(): void
    {
        $user = new User();
        $user->setEmail('new@example.com');
        $user->setPlainPassword('secret123');

        $this->filterCollection->method('isEnabled')->with('softdeleteable')->willReturn(true);
        $this->userRepository->method('findOneBy')->with(['email' => 'new@example.com'])->willReturn(null);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'secret123')
            ->willReturn('hashed_secret123');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())->method('flush');

        $registeredUser = $this->manager->registerUser($user, [
            'firstName' => 'Taras',
            'lastName' => 'Shevchenko',
        ], false);

        $this->assertSame($user, $registeredUser);
        $this->assertFalse($user->isVerified());
        $this->assertSame('hashed_secret123', $user->getPassword());
        $this->assertNull($user->getPlainPassword());
        $this->assertNotNull($user->getUserProfile());
        $this->assertSame('Taras', $user->getUserProfile()->getFirstName());
        $this->assertSame('Shevchenko', $user->getUserProfile()->getLastName());
        $this->assertNotNull($user->getLastVerificationEmailSentAt());
    }

    /**
     * @return void
     */
    public function testRegisterUserThrowsOnSoftDeletedAccount(): void
    {
        $user = new User();
        $user->setEmail('deleted@example.com');

        $existingUser = new User();
        $existingUser->setEmail('deleted@example.com');
        $existingUser->setDeletedAt(new DateTimeImmutable());

        $this->filterCollection->method('isEnabled')->with('softdeleteable')->willReturn(true);
        $this->filterCollection->expects($this->once())->method('disable')->with('softdeleteable');
        $this->filterCollection->expects($this->once())->method('enable')->with('softdeleteable');

        $this->userRepository->method('findOneBy')->with(['email' => 'deleted@example.com'])->willReturn($existingUser);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('user.account_deleted_restore');

        $this->manager->registerUser($user);
    }

    /**
     * @return void
     */
    public function testRestoreUser(): void
    {
        $user = new User();
        $user->setEmail('restore@example.com');
        $user->setDeletedAt(new DateTimeImmutable());

        $this->filterCollection->method('isEnabled')->with('softdeleteable')->willReturn(false);
        $this->userRepository->method('findOneBy')->with(['email' => 'restore@example.com'])->willReturn($user);

        $this->entityManager->expects($this->once())->method('flush');

        $restored = $this->manager->restoreUser('restore@example.com');

        $this->assertSame($user, $restored);
        $this->assertNull($user->getDeletedAt());
    }

    /**
     * @return void
     */
    public function testRestoreUserReturnsNullIfNotFoundOrNotDeleted(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $restored = $this->manager->restoreUser('nobody@example.com');
        $this->assertNull($restored);
    }
}
