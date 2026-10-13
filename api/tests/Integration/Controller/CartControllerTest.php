<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Cart;
use App\Entity\Product;
use Symfony\Component\HttpFoundation\Response;

class CartControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     */
    public function testGetCollectionCartUnauthorized(): void
    {
        $this->client->request('GET', '/api/carts');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetCollectionCartForbiddenForRegularUser(): void
    {
        $user = $this->createUser('cart_user_non_admin@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->client->request('GET', '/api/carts', [], [], $headers);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetCollectionCartAllowedForAdmin(): void
    {
        $admin = $this->createAdminUser('cart_admin@example.com');
        $headers = $this->getAuthHeaders($admin);

        $this->client->request('GET', '/api/carts', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('items', $content);
        $this->assertArrayHasKey('pagination', $content);
    }

    /**
     * @return void
     */
    public function testGetMyCartUnauthorized(): void
    {
        $this->client->request('GET', '/api/my-cart');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetMyCartCreatesAndReturnsCart(): void
    {
        $user = $this->createUser('my_cart_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->client->request('GET', '/api/my-cart', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('id', $content);
        $this->assertSame('0.00', $content['totalPrice']);

        // Call again to verify it returns the existing cart
        $this->client->request('GET', '/api/my-cart', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $secondContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame($content['id'], $secondContent['id']);
    }
}
