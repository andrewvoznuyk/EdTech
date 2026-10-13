<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded')]
final readonly class JWTDecodedListener
{
    /**
     * @param JWTDecodedEvent $event
     * @return void
     */
    public function __invoke(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        if (($payload['purpose'] ?? null) === '2fa_challenge') {
            $event->markAsInvalid();
        }
    }
}
