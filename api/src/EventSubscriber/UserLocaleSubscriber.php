<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

readonly class UserLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private LocaleSwitcher $localeSwitcher
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 0]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof User && $user->getLocale()) {
            $this->localeSwitcher->setLocale($user->getLocale());
            $event->getRequest()->setLocale($user->getLocale());
        }
    }
}
