<?php

namespace App\EntityListener;

use App\Entity\User;
use App\Service\Interface\UserAwareInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsDoctrineListener(event: Events::prePersist)]
readonly class UserAwareListener
{
    public function __construct(private Security $security) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof UserAwareInterface) {
            return;
        }

        if ($entity->getUser() !== null) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('Login required to perform this action.');
        }

        $entity->setUser($user);
    }
}
