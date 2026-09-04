<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Event\GoogleUserAuthenticatedEvent;
use App\Repository\UserRepository;
use App\Service\Interface\UserRegistrationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GoogleAuthSubscriber implements EventSubscriberInterface
{
    /**
     * @param UserRepository $userRepository
     * @param UserRegistrationInterface $registrationManager
     */
    public function __construct(
        private UserRepository $userRepository,
        private UserRegistrationInterface $registrationManager
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
        }

        $event->setUser($user);
    }
}
