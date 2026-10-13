<?php

namespace App\Controller;

use App\DTO\Common\PaginatedResult;
use App\DTO\Order\Request\OrderQueryDto;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Service\EntityService\Order\OrderPlacementServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class OrderController extends AbstractController
{
    /**
     * @param OrderPlacementServiceInterface $orderPlacementService
     */
    public function __construct(
        private readonly OrderPlacementServiceInterface $orderPlacementService,
    ) {
    }

    /**
     * @param User $user
     * @param OrderQueryDto|null $filterDto
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/my-orders', name: 'get_my_orders', methods: ['GET'])]
    public function getMyOrders(#[CurrentUser] User $user, #[MapQueryString] ?OrderQueryDto $filterDto = null): Response
    {
        $result = $this->orderPlacementService->getCollection($filterDto ?? new OrderQueryDto(), $user);

        return $this->json($result, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Order::GET_COLLECTION_ORDER,
                OrderItem::GET_ITEM_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
                PaginatedResult::GET_COLLECTION_PAGINATION,
            ],
        ]);
    }

    /**
     * @param OrderQueryDto|null $filterDto
     * @return Response
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/orders', name: 'get_collection_order', methods: ['GET'])]
    public function getCollection(#[MapQueryString] ?OrderQueryDto $filterDto = null): Response
    {
        $result = $this->orderPlacementService->getCollection($filterDto ?? new OrderQueryDto());

        return $this->json($result, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Order::GET_COLLECTION_ORDER,
                OrderItem::GET_ITEM_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
                User::GET_COLLECTION_USER,
                PaginatedResult::GET_COLLECTION_PAGINATION,
            ],
        ]);
    }

    /**
     * @param User $user
     * @param Order $order
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/orders/{id}', name: 'get_item_order', methods: ['GET'])]
    public function getItem(#[CurrentUser] User $user, Order $order): Response
    {
        $foundOrder = $this->orderPlacementService->getOrder($user, $order);

        return $this->json($foundOrder, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Order::GET_ITEM_ORDER,
                OrderItem::GET_ITEM_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }

    /**
     * @param User $user
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/orders/checkout', name: 'post_order_checkout', methods: ['POST'])]
    public function checkout(#[CurrentUser] User $user): Response
    {
        $order = $this->orderPlacementService->placeOrder($user);

        return $this->json($order, Response::HTTP_CREATED, [], [
            AbstractNormalizer::GROUPS => [
                Order::GET_ITEM_ORDER,
                OrderItem::GET_ITEM_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }

    /**
     * @param User $user
     * @param Order $order
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/orders/{id}/cancel', name: 'patch_order_cancel', methods: ['PATCH'])]
    public function cancelOrder(#[CurrentUser] User $user, Order $order): Response
    {
        $cancelledOrder = $this->orderPlacementService->cancelOrder($user, $order);

        return $this->json($cancelledOrder, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Order::GET_ITEM_ORDER,
                OrderItem::GET_ITEM_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }

    /**
     * @param Request $request
     * @param Order $order
     * @param Order $orderPayload
     * @return Response
     * @throws BadRequestHttpException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/orders/{id}/status', name: 'patch_order_status', methods: ['PATCH'])]
    public function updateStatus(
        Request $request,
        Order $order,
        #[MapRequestPayload(
            serializationContext: ['groups' => [Order::PATCH_ITEM_ORDER_STATUS]],
            validationGroups: [Order::PATCH_ITEM_ORDER_STATUS]
        )] Order $orderPayload,
    ): Response {
        if (!$request->getPayload()->has('status')) {
            throw new BadRequestHttpException('Field "status" is required.');
        }

        $updatedOrder = $this->orderPlacementService->updateStatus($order, $orderPayload->getStatus());

        return $this->json($updatedOrder, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Order::GET_ITEM_ORDER,
                OrderItem::GET_ITEM_ORDER_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }

    /**
     * @param Order $order
     * @return Response
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/orders/{id}', name: 'delete_item_order', methods: ['DELETE'])]
    public function deleteOrder(Order $order): Response
    {
        $this->orderPlacementService->deleteOrder($order);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
