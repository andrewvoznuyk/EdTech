<?php

namespace App\Controller;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
readonly class LogoutController
{
    /**
     * @param RefreshTokenManagerInterface $refreshTokenManager
     */
    public function __construct(
        private RefreshTokenManagerInterface $refreshTokenManager,
    ) {
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        $refreshTokenString = $request->cookies->get('refresh_token');

        if ($refreshTokenString) {
            $refreshToken = $this->refreshTokenManager->get($refreshTokenString);

            if ($refreshToken) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);

        $response->headers->clearCookie(
            'refresh_token',
            '/api/token/refresh',
            null,
            false,
            true,
            'lax'
        );

        return $response;
    }
}
