<?php

namespace App\Validator;

use Exception;
use Google_Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

readonly class GoogleTokenValidator
{
    /**
     * @param string $clientId
     */
    public function __construct(
        #[Autowire(env: 'GOOGLE_CLIENT_ID')]
        private string $clientId,
    ) {}

    /**
     * @param string|null $idToken
     * @return array
     */
    public function validateToken(?string $idToken): array
    {
        if (!$idToken) {
            throw new BadRequestHttpException('user.google_auth.token_required');
        }

        $client = new Google_Client(['client_id' => $this->clientId]);

        try {
            $payload = $client->verifyIdToken($idToken);
        } catch (Exception $e) {
            throw new BadRequestHttpException('user.google_auth.invalid_format');
        }

        if (!$payload) {
            throw new UnauthorizedHttpException('Bearer', 'user.google_auth.invalid_token');
        }

        if (empty($payload['email'])) {
            throw new BadRequestHttpException('user.google_auth.email_missing');
        }

        return $payload;
    }
}
