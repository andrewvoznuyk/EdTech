<?php

namespace App\EventSubscriber;

use App\Entity\File;
use App\Entity\User;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Enum\MimeType;
use App\Event\GoogleUserAuthenticatedEvent;
use App\Repository\UserRepository;
use App\Service\FileService;
use App\Service\Interface\UserRegistrationInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Uid\Uuid;

readonly class GoogleAuthSubscriber implements EventSubscriberInterface
{
    /**
     * @param UserRepository $userRepository
     * @param UserRegistrationInterface $registrationManager
     * @param FileService $fileService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private UserRepository $userRepository,
        private UserRegistrationInterface $registrationManager,
        private FileService $fileService,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            GoogleUserAuthenticatedEvent::class => 'onGoogleUserAuthenticated',
        ];
    }

    /**
     * @param GoogleUserAuthenticatedEvent $event
     * @return void
     */
    public function onGoogleUserAuthenticated(GoogleUserAuthenticatedEvent $event): void
    {
        $email = $event->getEmail();

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $newUser = new User();
            $newUser->setEmail($email);

            $user = $this->registrationManager->registerUser(
                user: $newUser,
                profileData: $event->getProfileData(),
                isVerified: true
            );

            $avatarUrl = $event->getAvatarUrl();
            if ($avatarUrl) {
                $this->processAndUploadAvatar($avatarUrl, $user);
            }
        }

        $event->setUser($user);
    }

    private function processAndUploadAvatar(string $url, User $user): void
    {
        $imageContent = @file_get_contents($url);
        if (!$imageContent) {
            return;
        }

        $mimeTypeEnum = MimeType::JPEG;
        $imageInfo = @getimagesizefromstring($imageContent);
        if ($imageInfo && isset($imageInfo['mime'])) {
            $parsedMime = MimeType::tryFrom($imageInfo['mime']);
            if ($parsedMime) {
                $mimeTypeEnum = $parsedMime;
            }
        }

        $fileEntity = new File();
        $fileEntity->setUser($user)
            ->setFileKey(Uuid::v4())
            ->setOriginalName('google_avatar_' . uniqid() . '.jpg')
            ->setMimeType($mimeTypeEnum)
            ->setSize(strlen($imageContent))
            ->setType(FileType::determineFileType($mimeTypeEnum))
            ->setStatus(FileStatus::PENDING)
            ->setVersion(1);

            $result = $this->fileService->uploadContent($imageContent, $fileEntity, $mimeTypeEnum->value);

            $fileEntity->setStatus(FileStatus::UPLOADED)
                ->setMetadata($result->toArray());

        $this->entityManager->persist($fileEntity);

        $profile = $user->getUserProfile();

        $profile?->setAvatar($fileEntity);

        $this->entityManager->flush();
    }
}
