<?php

namespace App\Service\User;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\UserRepository;
use App\Service\Interface\UserRegistrationInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsAlias(id: UserRegistrationInterface::class)]
readonly class UserRegistrationManager implements UserRegistrationInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private UserRepository              $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private TranslatorInterface         $translator,
    ) {}

    /**
     * @param User $user
     * @param array $profileData
     * @param bool $isVerified
     * @return User
     */
    public function registerUser(User $user, array $profileData = [], bool $isVerified = false): User
    {
        if ($this->isAccountSoftDeleted($user->getEmail())) {
            $errorMessage = $this->translator->trans('user.account_deleted_restore', [], 'validators');
            throw new ConflictHttpException($errorMessage);
        }

        $user->setIsVerified($isVerified);
        if (!$isVerified) {
            $user->setLastVerificationEmailSentAt(new DateTimeImmutable());
        }

        $this->assignProfile($user, $profileData);

        $this->handlePassword($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param string $email
     * @return bool
     */
    public function isAccountSoftDeleted(string $email): bool
    {
        $existingUser = $this->findUserWithSoftDeleteDisabled($email);

        return $existingUser && $existingUser->getDeletedAt() !== null;
    }

    /**
     * @param string $email
     * @return User|null
     */
    public function restoreUser(string $email): ?User
    {
        $user = $this->findUserWithSoftDeleteDisabled($email);

        if (!$user || $user->getDeletedAt() === null) {
            return null;
        }

        $user->setDeletedAt();
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param string $email
     * @return User|null
     */
    private function findUserWithSoftDeleteDisabled(string $email): ?User
    {
        $filters = $this->entityManager->getFilters();
        $softDeleteEnabled = $filters->isEnabled('softdeleteable');

        if ($softDeleteEnabled) {
            $filters->disable('softdeleteable');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($softDeleteEnabled) {
            $filters->enable('softdeleteable');
        }

        return $user;
    }

    /**
     * @param User $user
     * @param array $profileData
     * @return void
     */
    private function assignProfile(User $user, array $profileData): void
    {
        $profile = $user->getUserProfile() ?? new UserProfile();

        if (!empty($profileData['firstName'])) {
            $profile->setFirstName($profileData['firstName']);
        }
        if (!empty($profileData['lastName'])) {
            $profile->setLastName($profileData['lastName']);
        }

        $profile->setUser($user);
        $user->setUserProfile($profile);
    }

    /**
     * @param User $user
     * @return void
     */
    private function handlePassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();

        if (!$plainPassword) {
            return;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $user->eraseCredentials();
    }
}
