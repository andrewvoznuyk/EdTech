<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Product;
use App\Enum\AssetType;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     */
     public function testGetProductsCollectionUnauthorized(): void
     {
         $this->client->request('GET', '/api/products');
         $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
     }

    /**
     * @return void
     */
     public function testGetProductsCollectionAuthenticated(): void
     {
         $user = $this->createUser('product_viewer@example.com');
         $headers = $this->getAuthHeaders($user);

         $product = new Product();
         $product->setTitle('Public Test Product');
         $product->setPrice('49.99');
         $product->setAssetType(AssetType::WIDGET->value);
         $product->setDurationDays(14);
         $this->entityManager->persist($product);
         $this->entityManager->flush();

         $this->client->request('GET', '/api/products', [], [], $headers);
         $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

         $content = json_decode((string) $this->client->getResponse()->getContent(), true);
         $this->assertIsArray($content);
         $this->assertArrayHasKey('items', $content);
         $this->assertArrayHasKey('pagination', $content);
     }

    /**
     * @return void
     */
     public function testGetProductItemUnauthorized(): void
     {
         $this->client->request('GET', '/api/products/00000000-0000-0000-0000-000000000000');
         $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
     }

    /**
     * @return void
     */
     public function testGetProductItemAuthenticated(): void
     {
         $user = $this->createUser('product_item_viewer@example.com');
         $headers = $this->getAuthHeaders($user);

         $product = new Product();
         $product->setTitle('Single Item Test Product');
         $product->setPrice('99.99');
         $product->setAssetType(AssetType::WIDGET->value);
         $product->setDurationDays(30);
         $this->entityManager->persist($product);
         $this->entityManager->flush();

         $productId = $product->getId()->toRfc4122();

         $this->client->request('GET', '/api/products/' . $productId, [], [], $headers);
         $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

         $content = json_decode((string) $this->client->getResponse()->getContent(), true);
         $this->assertIsArray($content);
         $this->assertSame('Single Item Test Product', $content['title']);
         $this->assertSame('99.99', $content['price']);
     }

    /**
     * @return void
     */
    public function testCreateProductUnauthorized(): void
    {
        $payload = json_encode([
            'title' => 'Unauthorized Product',
            'price' => '10.00',
            'assetType' => AssetType::WIDGET->value,
            'durationDays' => 30,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/products', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testCreateProductForbiddenForRegularUser(): void
    {
        $user = $this->createUser('regular_user_product@example.com');
        $headers = $this->getAuthHeaders($user);

        $payload = json_encode([
            'title' => 'Forbidden Product',
            'price' => '10.00',
            'assetType' => AssetType::WIDGET->value,
            'durationDays' => 30,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/products', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testCreateProductAdmin(): void
    {
        $admin = $this->createAdminUser('admin_product_create@example.com');
        $headers = $this->getAuthHeaders($admin);

        $payload = json_encode([
            'title' => 'Admin Created Product',
            'price' => '120.50',
            'assetType' => AssetType::WIDGET->value,
            'durationDays' => 60,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/products', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame('Admin Created Product', $content['title']);
        $this->assertSame('120.50', $content['price']);

        $persisted = $this->entityManager->getRepository(Product::class)->findOneBy(['title' => 'Admin Created Product']);
        $this->assertNotNull($persisted);
    }

    /**
     * @return void
     */
    public function testUpdateProductAdmin(): void
    {
        $admin = $this->createAdminUser('admin_product_patch@example.com');
        $headers = $this->getAuthHeaders($admin);

        $product = new Product();
        $product->setTitle('Before Update Product');
        $product->setPrice('20.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(30);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $productId = $product->getId()->toRfc4122();

        $payload = json_encode([
            'title' => 'After Update Product',
            'price' => '25.00',
            'assetType' => AssetType::WIDGET->value,
            'durationDays' => 45,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', '/api/products/' . $productId, [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('After Update Product', $content['title']);
        $this->assertSame('25.00', $content['price']);
    }

    /**
     * @return void
     */
    public function testDeleteProductAdmin(): void
    {
        $admin = $this->createAdminUser('admin_product_delete@example.com');
        $headers = $this->getAuthHeaders($admin);

        $product = new Product();
        $product->setTitle('To Delete Product');
        $product->setPrice('30.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(30);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $productId = $product->getId()->toRfc4122();

        $this->client->request('DELETE', '/api/products/' . $productId, [], [], $headers);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        $deleted = $this->entityManager->getRepository(Product::class)->findOneBy(['id' => $product->getId()]);
        $this->assertNull($deleted);
    }
}
