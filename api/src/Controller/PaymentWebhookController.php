<?php

namespace App\Controller;


use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentWebhookController extends AbstractController
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @param Request $request
     * @param int|null $id
     * @return Response
     */
    #[Route('/api/webhook/payment-success/{id}', name: 'payment_webhook_by_id', methods: ['POST'])]
    #[Route('/api/webhook/payment-success', name: 'payment_webhook', methods: ['POST'])]
    public function handle(Request $request, ?int $id = null): Response
    {
        return new JsonResponse([]);
    }
}
