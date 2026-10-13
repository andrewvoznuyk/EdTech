<?php

namespace App\Tests\Integration\Controller;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Enum\AssetType;
use Symfony\Component\HttpFoundation\Response;

class CartItemControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     */
    public function testAddCartItemUnauthorized(): void
    {
        $this->client->request('POST', '/api/cart-items', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['quantity' => 1]));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testAddCartItemSuccessAndIncrement(): void
    {
        $user = $this->createUser('cart_item_user1@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Test Cart Product');
        $product->setPrice('100.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(30);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // 1. First add
        $payload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 2,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame(2, $content['quantity']);
        $this->assertSame('200.00', $content['totalPrice']);
        $cartItemId = $content['id'];

        // 2. Add again with same product - should increment
        $payloadSecond = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 3,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $payloadSecond);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $contentSecond = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($contentSecond);
        $this->assertSame($cartItemId, $contentSecond['id']);
        $this->assertSame(5, $contentSecond['quantity']);
        $this->assertSame('500.00', $contentSecond['totalPrice']);
    }

    /**
     * @return void
     */
    public function testUpdateQuantitySuccess(): void
    {
        $user = $this->createUser('cart_item_user2@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Patch Test Product');
        $product->setPrice('50.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(15);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $addPayload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $addPayload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $createdItem = json_decode((string) $this->client->getResponse()->getContent(), true);
        $cartItemId = $createdItem['id'];

        // PATCH quantity
        $patchPayload = json_encode([
            'quantity' => 7,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', sprintf('/api/cart-items/%s', $cartItemId), [], [], $headers, $patchPayload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $patchedContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($patchedContent);
        $this->assertSame(7, $patchedContent['quantity']);
        $this->assertSame('350.00', $patchedContent['totalPrice']);
    }

    /**
     * @return void
     */
    public function testUpdateQuantityForbiddenForOtherUser(): void
    {
        $owner = $this->createUser('owner_patch@example.com');
        $otherUser = $this->createUser('other_patch@example.com');
        $ownerHeaders = $this->getAuthHeaders($owner);
        $otherHeaders = $this->getAuthHeaders($otherUser);

        $product = new Product();
        $product->setTitle('Security Patch Product');
        $product->setPrice('25.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(10);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $addPayload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $ownerHeaders, $addPayload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $createdItem = json_decode((string) $this->client->getResponse()->getContent(), true);
        $cartItemId = $createdItem['id'];

        // Other user attempts to PATCH
        $patchPayload = json_encode([
            'quantity' => 10,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', sprintf('/api/cart-items/%s', $cartItemId), [], [], $otherHeaders, $patchPayload);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testRemoveItemSuccess(): void
    {
        $user = $this->createUser('cart_delete_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Delete Test Product');
        $product->setPrice('30.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $addPayload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 2,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $addPayload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $createdItem = json_decode((string) $this->client->getResponse()->getContent(), true);
        $cartItemId = $createdItem['id'];

        // DELETE
        $this->client->request('DELETE', sprintf('/api/cart-items/%s', $cartItemId), [], [], $headers);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        // Verify deleted
        $found = $this->entityManager->getRepository(CartItem::class)->findOneBy(['id' => $cartItemId]);
        $this->assertNull($found);
    }

    /**
     * @return void
     */
    public function testRemoveItemForbiddenForOtherUser(): void
    {
        $owner = $this->createUser('delete_owner@example.com');
        $otherUser = $this->createUser('delete_other@example.com');
        $ownerHeaders = $this->getAuthHeaders($owner);
        $otherHeaders = $this->getAuthHeaders($otherUser);

        $product = new Product();
        $product->setTitle('Delete Forbidden Product');
        $product->setPrice('15.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $addPayload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $ownerHeaders, $addPayload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $createdItem = json_decode((string) $this->client->getResponse()->getContent(), true);
        $cartItemId = $createdItem['id'];

        // Other user attempts to DELETE
        $this->client->request('DELETE', sprintf('/api/cart-items/%s', $cartItemId), [], [], $otherHeaders);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetCollectionCartItemAllowedForAdmin(): void
    {
        $admin = $this->createAdminUser('cart_item_admin@example.com');
        $headers = $this->getAuthHeaders($admin);

        $this->client->request('GET', '/api/cart-items', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('items', $content);
        $this->assertArrayHasKey('pagination', $content);
    }

    /**
     * @return void
     */
    public function testAddCartItemNonExistentProductReturnsNotFound(): void
    {
        $user = $this->createUser('cart_item_notfound@example.com');
        $headers = $this->getAuthHeaders($user);

        $payload = json_encode([
            'productId' => '00000000-0000-0000-0000-000000000000',
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testAddCartItemInvalidQuantityReturnsUnprocessable(): void
    {
        $user = $this->createUser('cart_item_invalid_qty@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Validation Test Product');
        $product->setPrice('20.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $payload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => -2,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testUpdateQuantityInvalidReturnsBadRequest(): void
    {
        $user = $this->createUser('cart_item_patch_invalid@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Patch Invalid Test Product');
        $product->setPrice('20.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $addPayload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $addPayload);
        $createdItem = json_decode((string) $this->client->getResponse()->getContent(), true);

        $patchPayload = json_encode([
            'quantity' => 0,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', sprintf('/api/cart-items/%s', $createdItem['id']), [], [], $headers, $patchPayload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testAddCartItemQuantityExceedingMaxReturnsBadRequest(): void
    {
        $user = $this->createUser('cart_item_max_qty@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Max Qty Test Product');
        $product->setPrice('20.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $payload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 100,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testAddCartItemWithSoftDeletedProductReturnsNotFound(): void
    {
        $user = $this->createUser('cart_item_deleted_prod@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Deleted Product');
        $product->setPrice('20.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $product->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $payload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 1,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testRemoveItemUpdatesMyCartImmediately(): void
    {
        $user = $this->createUser('cart_sync_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Sync Product');
        $product->setPrice('50.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // 1. Add item
        $addPayload = json_encode([
            'productId' => (string) $product->getId(),
            'quantity' => 2,
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/cart-items', [], [], $headers, $addPayload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $createdItem = json_decode((string) $this->client->getResponse()->getContent(), true);

        // 2. Verify in my-cart
        $this->client->request('GET', '/api/my-cart', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $cartContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('100.00', $cartContent['totalPrice']);
        $this->assertSame(2, $cartContent['totalQuantity']);
        $this->assertCount(1, $cartContent['cartItems']);

        // 3. Delete item
        $this->client->request('DELETE', sprintf('/api/cart-items/%s', $createdItem['id']), [], [], $headers);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        // 4. Verify my-cart is empty and zero immediately
        $this->client->request('GET', '/api/my-cart', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $emptyCartContent = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('0.00', $emptyCartContent['totalPrice']);
        $this->assertSame(0, $emptyCartContent['totalQuantity']);
        $this->assertCount(0, $emptyCartContent['cartItems']);
    }
}
