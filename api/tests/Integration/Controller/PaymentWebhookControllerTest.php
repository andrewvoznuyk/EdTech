<?php

namespace App\Tests\Integration\Controller;

use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     */
    public function testPaymentWebhookWithoutId(): void
    {
        $payload = json_encode(['status' => 'success', 'order_id' => '12345'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/webhook/payment-success', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame([], $content);
    }

    /**
     * @return void
     */
    public function testPaymentWebhookWithId(): void
    {
        $payload = json_encode(['status' => 'success'], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/webhook/payment-success/42', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame([], $content);
    }
}
