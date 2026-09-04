<?php

namespace App\EventListener;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: User::class)]
class UserRemoveListener
{
    /**
     * @param User $user
     * @param PreRemoveEventArgs $args
     * @return void
     */
    public function preRemove(User $user, PreRemoveEventArgs $args): void
    {
        $em = $args->getObjectManager();

        $tokens = $em->getRepository(RefreshToken::class)->findBy(['username' => $user->getUserIdentifier()]);

        foreach ($tokens as $token) {
            $em->remove($token);
        }
    }
}
