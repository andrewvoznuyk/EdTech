<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\AssetType;
use App\Enum\OrderStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class OrderItemControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     */
    public function testGetOrderItemsUnauthorized(): void
    {
        $this->client->request('GET', sprintf('/api/orders/%s/items', Uuid::v4()));
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetOrderItemsSuccessForOwner(): void
    {
        $user = $this->createUser('owner_order_items@example.com');
        $headers = $this->getAuthHeaders($user);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalPrice('80.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);

        $product = new Product();
        $product->setTitle('Order Items Test Product');
        $product->setPrice('40.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(30);
        $this->entityManager->persist($product);

        $orderItem = new OrderItem();
        $orderItem->setCustomerOrder($order);
        $orderItem->setProduct($product);
        $orderItem->setQuantity(2);
        $orderItem->calculateTotalPrice();
        $order->addOrderItem($orderItem);
        $this->entityManager->persist($orderItem);

        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/api/orders/%s/items', $order->getId()), [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
        $this->assertSame((string) $orderItem->getId(), $content[0]['id']);
        $this->assertSame(2, $content[0]['quantity']);
        $this->assertSame('80.00', $content[0]['totalPrice']);
    }

    /**
     * @return void
     */
    public function testGetOrderItemsSuccessForAdmin(): void
    {
        $owner = $this->createUser('owner_items_admin_test@example.com');
        $admin = $this->createAdminUser('admin_order_items_view@example.com');
        $adminHeaders = $this->getAuthHeaders($admin);

        $order = new Order();
        $order->setUser($owner);
        $order->setTotalPrice('0.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/api/orders/%s/items', $order->getId()), [], [], $adminHeaders);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetOrderItemsForbiddenForStranger(): void
    {
        $owner = $this->createUser('owner_secret_items@example.com');
        $stranger = $this->createUser('stranger_items_spy@example.com');
        $strangerHeaders = $this->getAuthHeaders($stranger);

        $order = new Order();
        $order->setUser($owner);
        $order->setTotalPrice('0.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/api/orders/%s/items', $order->getId()), [], [], $strangerHeaders);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetOrderItemsNotFoundForNonExistentOrder(): void
    {
        $user = $this->createUser('user_non_existent_order@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->client->request('GET', sprintf('/api/orders/%s/items', Uuid::v4()), [], [], $headers);
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetItemUnauthorized(): void
    {
        $this->client->request('GET', sprintf('/api/order-items/%s', Uuid::v4()));
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetItemSuccessForOwner(): void
    {
        $user = $this->createUser('get_order_item_user@example.com');
        $headers = $this->getAuthHeaders($user);

        $order = new Order();
        $order->setUser($user);
        $order->setTotalPrice('22.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);

        $product = new Product();
        $product->setTitle('View Item Product');
        $product->setPrice('22.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);

        $orderItem = new OrderItem();
        $orderItem->setCustomerOrder($order);
        $orderItem->setProduct($product);
        $orderItem->setQuantity(1);
        $orderItem->calculateTotalPrice();
        $this->entityManager->persist($orderItem);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/api/order-items/%s', $orderItem->getId()), [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame((string) $orderItem->getId(), $content['id']);
        $this->assertSame((string) $order->getId(), $content['orderId']);
        $this->assertSame('22.00', $content['totalPrice']);
        $this->assertSame('22.00', $content['unitPrice']);
        $this->assertArrayHasKey('createdAt', $content);
        $this->assertNotNull($content['createdAt']);
    }

    /**
     * @return void
     */
    public function testGetItemForbiddenForStranger(): void
    {
        $owner = $this->createUser('owner_single_item@example.com');
        $stranger = $this->createUser('stranger_single_item@example.com');
        $strangerHeaders = $this->getAuthHeaders($stranger);

        $order = new Order();
        $order->setUser($owner);
        $order->setTotalPrice('0.00');
        $order->setStatus(OrderStatus::PENDING);
        $this->entityManager->persist($order);

        $product = new Product();
        $product->setTitle('Single Item Stranger Product');
        $product->setPrice('10.00');
        $product->setAssetType(AssetType::WIDGET->value);
        $product->setDurationDays(5);
        $this->entityManager->persist($product);

        $orderItem = new OrderItem();
        $orderItem->setCustomerOrder($order);
        $orderItem->setProduct($product);
        $orderItem->setQuantity(1);
        $orderItem->calculateTotalPrice();
        $this->entityManager->persist($orderItem);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/api/order-items/%s', $orderItem->getId()), [], [], $strangerHeaders);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGetCollectionAllowedForAdmin(): void
    {
        $admin = $this->createAdminUser('admin_order_items@example.com');
        $headers = $this->getAuthHeaders($admin);

        $this->client->request('GET', '/api/order-items', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('items', $content);
        $this->assertArrayHasKey('pagination', $content);
    }

    /**
     * @return void
     */
    public function testGetCollectionForbiddenForRegularUser(): void
    {
        $user = $this->createUser('regular_user_all_items@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->client->request('GET', '/api/order-items', [], [], $headers);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
