<?php

namespace App\Tests\Integration\Controller;

use App\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class LogoutControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @var RefreshTokenManagerInterface
     */
    private RefreshTokenManagerInterface $refreshTokenManager;

    /**
     * @var RefreshTokenGeneratorInterface
     */
    private RefreshTokenGeneratorInterface $refreshTokenGenerator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshTokenManager = self::getContainer()->get('gesdinet_jwt_refresh_token.refresh_token_manager');
        $this->refreshTokenGenerator = self::getContainer()->get(RefreshTokenGeneratorInterface::class);
    }

    /**
     * @return void
     */
    public function testLogoutWithoutCookie(): void
    {
        $this->client->request('POST', '/api/token/refresh/logout');
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        $cookies = $this->client->getResponse()->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'refresh_token') {
                $found = true;
                $this->assertLessThanOrEqual(time(), $cookie->getExpiresTime());
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @return void
     */
    public function testLogoutWithValidRefreshToken(): void
    {
        $user = $this->createUser('logout_user@example.com');
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, 3600);
        $this->refreshTokenManager->save($refreshToken);

        $tokenString = $refreshToken->getRefreshToken();
        $this->assertNotNull($this->refreshTokenManager->get($tokenString));

        $cookie = new Cookie('refresh_token', $tokenString, strtotime('+1 hour'), '/api/token/refresh');
        $this->client->getCookieJar()->set($cookie);

        $this->client->request('POST', '/api/token/refresh/logout');
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        $this->assertNull($this->refreshTokenManager->get($tokenString));
    }

    /**
     * @return void
     */
    public function testLogoutWithUnknownRefreshToken(): void
    {
        $cookie = new Cookie('refresh_token', 'non_existing_token_string', strtotime('+1 hour'), '/api/token/refresh');
        $this->client->getCookieJar()->set($cookie);

        $this->client->request('POST', '/api/token/refresh/logout');
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }
}
