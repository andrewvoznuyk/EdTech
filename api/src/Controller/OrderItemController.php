<?php

namespace App\Controller;

use App\DTO\Common\PaginatedResult;
use App\DTO\OrderItem\Request\OrderItemQueryDto;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Service\EntityService\OrderItem\OrderItemServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;

class OrderItemController extends AbstractController
{
    /**
     * @param OrderItemServiceInterface $orderItemService
     */
    public function __construct(
        private readonly OrderItemServiceInterface $orderItemService,
    ) {
    }

    /**
     * @param OrderItemQueryDto|null $filterDto
     * @return Response
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/order-items', name: 'get_collection_order_item', methods: ['GET'])]
    public function getCollection(#[MapQueryString] ?OrderItemQueryDto $filterDto = null): Response
    {
        $result = $this->orderItemService->getCollection($filterDto ?? new OrderItemQueryDto());

        return $this->json($result, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                OrderItem::GET_COLLECTION_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
                PaginatedResult::GET_COLLECTION_PAGINATION,
            ],
        ]);
    }

    /**
     * @param User $user
     * @param Uuid $orderId
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/orders/{orderId}/items', name: 'get_order_items', methods: ['GET'])]
    public function getOrderItems(#[CurrentUser] User $user, Uuid $orderId): Response
    {
        $items = $this->orderItemService->getItemsByOrderId($user, $orderId);

        return $this->json($items, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                OrderItem::GET_COLLECTION_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }

    /**
     * @param User $user
     * @param OrderItem $orderItem
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/order-items/{id}', name: 'get_item_order_item', methods: ['GET'])]
    public function getItem(
        #[CurrentUser] User $user,
        OrderItem $orderItem,
    ): Response {
        $foundOrderItem = $this->orderItemService->getItem($user, $orderItem);

        return $this->json($foundOrderItem, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                OrderItem::GET_ITEM_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }
}
