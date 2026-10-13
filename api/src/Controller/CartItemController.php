<?php

namespace App\Controller;

use App\DTO\CartItem\Request\CartItemQueryDto;
use App\DTO\Common\PaginatedResult;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Service\EntityService\CartItem\CartItemServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/cart-items')]
class CartItemController extends AbstractController
{
    /**
     * @param CartItemServiceInterface $cartItemService
     */
    public function __construct(
        private readonly CartItemServiceInterface $cartItemService,
    ) {
    }

    /**
     * @param CartItemQueryDto|null $filterDto
     * @return Response
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('', name: 'get_collection_cart_item', methods: ['GET'])]
    public function getCollection(#[MapQueryString] ?CartItemQueryDto $filterDto = null): Response
    {
        $result = $this->cartItemService->getCollection($filterDto ?? new CartItemQueryDto());

        return $this->json($result, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                CartItem::GET_COLLECTION_CART_ITEM,
                Product::GET_ITEM_PRODUCT,
                PaginatedResult::GET_COLLECTION_PAGINATION,
            ],
        ]);
    }

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'post_collection_cart_item', methods: ['POST'])]
    public function addItem(#[CurrentUser] User $user, #[MapRequestPayload(serializationContext: ['groups' => [CartItem::POST_COLLECTION_CART_ITEM]], validationGroups: [CartItem::POST_COLLECTION_CART_ITEM])] CartItem $cartItem): Response
    {
        $savedCartItem = $this->cartItemService->addItem($user, $cartItem);

        return $this->json($savedCartItem, Response::HTTP_CREATED, [], [
            AbstractNormalizer::GROUPS => [
                CartItem::GET_ITEM_CART_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @param CartItem $cartItemPayload
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/{id}', name: 'patch_item_cart_item', methods: ['PATCH'])]
    public function updateQuantity(#[CurrentUser] User $user, CartItem $cartItem, #[MapRequestPayload(serializationContext: ['groups' => [CartItem::PATCH_ITEM_CART_ITEM]], validationGroups: [CartItem::PATCH_ITEM_CART_ITEM])] CartItem $cartItemPayload): Response
    {
        $updatedCartItem = $this->cartItemService->updateQuantity($user, $cartItem, $cartItemPayload->getQuantity() ?? 1);

        return $this->json($updatedCartItem, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                CartItem::GET_ITEM_CART_ITEM,
                Product::GET_ITEM_PRODUCT,
            ],
        ]);
    }

    /**
     * @param User $user
     * @param CartItem $cartItem
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/{id}', name: 'delete_item_cart_item', methods: ['DELETE'])]
    public function removeItem(#[CurrentUser] User $user, CartItem $cartItem,): Response {
        $this->cartItemService->removeItem($user, $cartItem);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
