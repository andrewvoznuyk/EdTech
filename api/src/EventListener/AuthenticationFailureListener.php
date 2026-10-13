<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Events::AUTHENTICATION_FAILURE, method: 'onAuthenticationFailure')]
readonly class AuthenticationFailureListener
{
    /**
     * @param AuthenticationFailureEvent $event
     * @return void
     */
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response) {
            return;
        }

        $data = json_decode($response->getContent() ?: '{}', true) ?? [];
        $data['errorCode'] = $event->getException()->getMessageKey();

        $response->setContent(json_encode($data));
    }
}
