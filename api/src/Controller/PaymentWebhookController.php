<?php

namespace App\Controller;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\EntityService\Order\OrderPlacementServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentWebhookController extends AbstractController
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param SerializerInterface $serializer
     * @param OrderPlacementServiceInterface $orderPlacementService
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SerializerInterface $serializer,
        private readonly OrderPlacementServiceInterface $orderPlacementService,
    ) {
    }

    /**
     * @param string|int $id
     * @return Response
     */
    #[Route('/api/webhook/payment-success/{id}', name: 'payment_webhook_by_id', methods: ['POST'])]
    public function handleById(string|int $id): Response
    {
        return $this->processOrderPayment($id);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/webhook/payment-success', name: 'payment_webhook', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $payload = $request->getPayload();
        $orderIdentifier = $payload->has('order_id')
            ? $payload->getString('order_id')
            : ($payload->has('id') ? $payload->getString('id') : null);

        return $this->processOrderPayment($orderIdentifier);
    }

    /**
     * @param string|int|null $orderIdentifier
     * @return Response
     */
    private function processOrderPayment(string|int|null $orderIdentifier): Response
    {
        if ($orderIdentifier === null || !is_scalar($orderIdentifier) || !Uuid::isValid((string) $orderIdentifier)) {
            return new JsonResponse([]);
        }

        $orderRepository = $this->entityManager->getRepository(Order::class);
        $order = $orderRepository->findOneBy(['id' => Uuid::fromString((string) $orderIdentifier)]);

        if ($order instanceof Order) {
            if ($order->getStatus() !== OrderStatus::PAID) {
                $this->orderPlacementService->updateStatus($order, OrderStatus::PAID);
            }

            return new JsonResponse([
                'order_id' => $order->getId()?->toRfc4122(),
            ]);
        }

        return new JsonResponse([]);
    }
}
