<?php

namespace App\Controller;

use App\Entity\Order;
use App\Event\OrderPaidEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentWebhookController extends AbstractController
{
    #[Route('/webhook/payment-success', name: 'payment_webhook', methods: ['POST'])]
    public function handle(Request $request, EntityManagerInterface $em, EventDispatcherInterface $eventDispatcher): Response {
        $payload = json_decode($request->getContent(), true);


        $orderId = $payload['order_id']?? null;

        if (!$orderId) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        $order = $em->getRepository(Order::class)->find($orderId);

        if (!$order) {
            return new Response('Order not found', Response::HTTP_NOT_FOUND);
        }

        if ($order->getStatus() === 'paid') {
            return new Response('Already processed', Response::HTTP_OK);
        }

        $order->setStatus('paid');
        $em->flush();

        $event = new OrderPaidEvent($order);
        $eventDispatcher->dispatch($event);

        return new Response('Webhook handled successfully', Response::HTTP_OK);
    }
}
