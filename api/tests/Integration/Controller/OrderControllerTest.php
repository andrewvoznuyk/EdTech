<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\Product;
use App\Enum\AssetType;
use App\Enum\OrderStatus;
use Symfony\Component\HttpFoundation\Response;

class OrderControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     */
    public function testPlaceOrderUnauthorized(): void
    {
        $this->client->request('POST', '/api/orders/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testPlaceOrderFailsWhenCartIsEmpty(): void
    {
        $user = $this->createUser('empty_cart_order@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->client->request('POST', '/api/orders/checkout', [], [], $headers);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testPlaceOrderSuccessAndClearsCart(): void
    {
        $user = $this->createUser('order_success_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $product = new Product();
        $product->setTitle('Order Test Product');
        $product->setPrice('75.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(30);
        $this->entityManager->persist($product);

        $cart = new Cart();
        $cart->setUser($user);
        $user->setCart($cart);
        $this->entityManager->persist($cart);

        $cartItem = new CartItem();
        $cartItem->setProduct($product);
        $cartItem->setQuantity(2);
        $cart->addCartItem($cartItem);
        $this->entityManager->persist($cartItem);

        $this->entityManager->flush();

        $this->client->request('POST', '/api/orders/checkout', [], [], $headers);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('id', $content);
        $this->assertSame('150.00', $content['totalPrice']);
        $this->assertSame(OrderStatus::PENDING->value, $content['status']);
        $this->assertCount(1, $content['orderItems']);

        // Verify cart is cleared
        $this->entityManager->clear();
        $reloadedCart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
        $this->assertNotNull($reloadedCart);
        $this->assertCount(0, $reloadedCart->getCartItems());
    }

    /**
     * @return void
     */
    public function testPlaceOrderFailsWhenUserIsDeleted(): void
    {
        $user = $this->createUser('deleted_checkout_user@example.com');
        $userHeaders = $this->getAuthHeaders($user);

        // Soft delete user
        $user->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->client->request('POST', '/api/orders/checkout', [], [], $userHeaders);
        $this->assertContains($this->client->getResponse()->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
    }

    /**
     * @return void
     */
    public function testGetMyOrdersUnauthorized(): void
    {
        $this->client->request('GET', '/api/my-orders');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetMyOrdersSuccess(): void
    {
        $user = $this->createUser('my_orders_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalPrice('99.99');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/my-orders', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('items', $content);
        $this->assertArrayHasKey('pagination', $content);
        $this->assertCount(1, $content['items']);
        $this->assertSame('99.99', $content['items'][0]['totalPrice']);
    }

    /**
     * @return void
     */
    public function testGetMyOrdersOrderingAscAndDesc(): void
    {
        $user = $this->createUser('my_orders_sort@example.com');
        $headers = $this->getAuthHeaders($user);

        $order1 = new Order();
        $order1->setUser($user);
        $order1->setTotalPrice('10.00');
        $order1->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order1);
        $this->entityManager->flush();

        // Small delay to ensure timestamp difference
        sleep(1);

        $order2 = new Order();
        $order2->setUser($user);
        $order2->setTotalPrice('20.00');
        $order2->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order2);
        $this->entityManager->flush();

        // 1. Default (DESC) - newest first
        $this->client->request('GET', '/api/my-orders', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $contentDefault = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame((string) $order2->getId(), $contentDefault['items'][0]['id']);
        $this->assertSame((string) $order1->getId(), $contentDefault['items'][1]['id']);

        // 2. Explicit ASC (?order=ASC) - oldest first
        $this->client->request('GET', '/api/my-orders?order=ASC', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $contentAsc = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame((string) $order1->getId(), $contentAsc['items'][0]['id']);
        $this->assertSame((string) $order2->getId(), $contentAsc['items'][1]['id']);

        // 3. Explicit DESC (?order=DESC)
        $this->client->request('GET', '/api/my-orders?order=DESC', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $contentDesc = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame((string) $order2->getId(), $contentDesc['items'][0]['id']);
        $this->assertSame((string) $order1->getId(), $contentDesc['items'][1]['id']);

        // 4. Test lowercase ?order=asc
        $this->client->request('GET', '/api/my-orders?order=asc', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $contentAscLower = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame((string) $order1->getId(), $contentAscLower['items'][0]['id']);
        $this->assertSame((string) $order2->getId(), $contentAscLower['items'][1]['id']);

        // 5. Test lowercase ?order=desc
        $this->client->request('GET', '/api/my-orders?order=desc', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $contentDescLower = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame((string) $order2->getId(), $contentDescLower['items'][0]['id']);
        $this->assertSame((string) $order1->getId(), $contentDescLower['items'][1]['id']);
    }

    /**
     * @return void
     */
    public function testGetCollectionOrdersForbiddenForRegularUser(): void
    {
        $user = $this->createUser('regular_orders_list@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->client->request('GET', '/api/orders', [], [], $headers);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetCollectionOrdersAllowedForAdmin(): void
    {
        $admin = $this->createAdminUser('admin_orders_list@example.com');
        $headers = $this->getAuthHeaders($admin);

        $this->client->request('GET', '/api/orders', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('items', $content);
        $this->assertArrayHasKey('pagination', $content);
    }

    /**
     * @return void
     */
    public function testGetItemOrderForbiddenForStranger(): void
    {
        $owner = $this->createUser('order_owner@example.com');
        $stranger = $this->createUser('order_stranger@example.com');
        $strangerHeaders = $this->getAuthHeaders($stranger);

        $order = new Order();
        $order->setUser($owner);
        $order->setTotalPrice('50.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/api/orders/%s', $order->getId()), [], [], $strangerHeaders);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetItemOrderSuccessForOwner(): void
    {
        $owner = $this->createUser('order_owner_view@example.com');
        $ownerHeaders = $this->getAuthHeaders($owner);

        $order = new Order();
        $order->setUser($owner);
        $order->setTotalPrice('50.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/api/orders/%s', $order->getId()), [], [], $ownerHeaders);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame((string) $order->getId(), $content['id']);
        $this->assertSame('50.00', $content['totalPrice']);
    }

    /**
     * @return void
     */
    public function testPatchOrderCancelSuccess(): void
    {
        $owner = $this->createUser('order_cancel_user@example.com');
        $ownerHeaders = $this->getAuthHeaders($owner);

        $order = new Order();
        $order->setUser($owner);
        $order->setTotalPrice('80.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('PATCH', sprintf('/api/orders/%s/cancel', $order->getId()), [], [], $ownerHeaders);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame(OrderStatus::CANCELLED->value, $content['status']);
    }

    /**
     * @return void
     */
    public function testPatchOrderCancelFailsWhenNotPending(): void
    {
        $owner = $this->createUser('order_cancel_fail_user@example.com');
        $ownerHeaders = $this->getAuthHeaders($owner);

        $order = new Order();
        $order->setUser($owner);
        $order->setTotalPrice('80.00');
        $order->setStatus(OrderStatus::PAID);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('PATCH', sprintf('/api/orders/%s/cancel', $order->getId()), [], [], $ownerHeaders);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testPatchOrderStatusForbiddenForRegularUser(): void
    {
        $user = $this->createUser('reg_status_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalPrice('30.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $payload = json_encode(['status' => 'paid'], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', sprintf('/api/orders/%s/status', $order->getId()), [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testPatchOrderStatusAllowedForAdmin(): void
    {
        $admin = $this->createAdminUser('admin_status_user@example.com');
        $adminHeaders = $this->getAuthHeaders($admin);

        $order = new Order();
        $order->setUser($admin);
        $order->setTotalPrice('30.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $payload = json_encode(['status' => 'paid'], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', sprintf('/api/orders/%s/status', $order->getId()), [], [], $adminHeaders, $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame(OrderStatus::PAID->value, $content['status']);
    }

    /**
     * @return void
     */
    public function testDeleteItemOrderForbiddenForRegularUser(): void
    {
        $user = $this->createUser('reg_delete_order_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalPrice('40.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('DELETE', sprintf('/api/orders/%s', $order->getId()), [], [], $headers);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testDeleteItemOrderAllowedForAdmin(): void
    {
        $admin = $this->createAdminUser('admin_delete_order_user@example.com');
        $adminHeaders = $this->getAuthHeaders($admin);

        $order = new Order();
        $order->setUser($admin);
        $order->setTotalPrice('40.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('DELETE', sprintf('/api/orders/%s', $order->getId()), [], [], $adminHeaders);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        // Verify soft deleted
        $this->entityManager->clear();
        $found = $this->entityManager->getRepository(Order::class)->findOneBy(['id' => $order->getId()]);
        $this->assertNull($found);
    }

    /**
     * @return void
     */
    public function testUpdateStatusFailsWhenStatusOmitted(): void
    {
        $admin = $this->createAdminUser('admin_status_empty@example.com');
        $adminHeaders = $this->getAuthHeaders($admin);

        $order = new Order();
        $order->setUser($admin);
        $order->setStatus(OrderStatus::PENDING);
        $order->setTotalPrice('30.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $payload = json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', sprintf('/api/orders/%s/status', $order->getId()), [], [], $adminHeaders, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testUpdateStatusFailsOnInvalidTransition(): void
    {
        $admin = $this->createAdminUser('admin_status_invalid_trans@example.com');
        $adminHeaders = $this->getAuthHeaders($admin);

        $order = new Order();
        $order->setUser($admin);
        $order->setStatus(OrderStatus::CANCELLED);
        $order->setTotalPrice('30.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $payload = json_encode(['status' => 'paid'], JSON_THROW_ON_ERROR);

        $this->client->request('PATCH', sprintf('/api/orders/%s/status', $order->getId()), [], [], $adminHeaders, $payload);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testDeleteOrderFailsWhenOrderIsPaid(): void
    {
        $admin = $this->createAdminUser('admin_delete_paid@example.com');
        $adminHeaders = $this->getAuthHeaders($admin);

        $order = new Order();
        $order->setUser($admin);
        $order->setStatus(OrderStatus::PAID);
        $order->setTotalPrice('30.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('DELETE', sprintf('/api/orders/%s', $order->getId()), [], [], $adminHeaders);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testOrderSerializationIncludesCreatedAtAndUserForAdmin(): void
    {
        $admin = $this->createAdminUser('admin_view_user_order@example.com');
        $adminHeaders = $this->getAuthHeaders($admin);

        $order = new Order();
        $order->setUser($admin);
        $order->setStatus(OrderStatus::PENDING);
        $order->setTotalPrice('30.00');
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/orders', [], [], $adminHeaders);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('items', $content);
        $this->assertNotEmpty($content['items']);

        $firstItem = $content['items'][0];
        $this->assertArrayHasKey('createdAt', $firstItem);
        $this->assertNotNull($firstItem['createdAt']);
        $this->assertArrayHasKey('user', $firstItem);
        $this->assertNotNull($firstItem['user']);
        $this->assertArrayHasKey('email', $firstItem['user']);
    }
}
