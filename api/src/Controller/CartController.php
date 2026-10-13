<?php

namespace App\Controller;

use App\DTO\Cart\Request\CartQueryDto;
use App\DTO\Common\PaginatedResult;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Service\EntityService\Cart\CartServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class CartController extends AbstractController
{
    /**
     * @param CartServiceInterface $cartService
     */
    public function __construct(
        private readonly CartServiceInterface $cartService,
    ) {
    }

    /**
     * @param CartQueryDto|null $filterDto
     * @return Response
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/carts', name: 'get_collection_cart', methods: ['GET'])]
    public function getCollectionCart(#[MapQueryString] ?CartQueryDto $filterDto = null): Response
    {
        $result = $this->cartService->getCollection($filterDto ?? new CartQueryDto());

        return $this->json($result, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Cart::GET_COLLECTION_CART,
                CartItem::GET_ITEM_CART_ITEM,
                Product::GET_ITEM_PRODUCT,
                User::GET_COLLECTION_USER,
                PaginatedResult::GET_COLLECTION_PAGINATION,
            ],
        ]);
    }

    /**
     * @param User $user
     * @return Response
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/api/my-cart', name: 'get_item_cart', methods: ['GET'])]
    public function getCart(#[CurrentUser] User $user): Response
    {
        $cart = $this->cartService->getCart($user);

        return $this->json($cart, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Cart::GET_ITEM_CART,
                CartItem::GET_ITEM_CART_ITEM,
                Product::GET_ITEM_PRODUCT,
                User::GET_ITEM_USER,
            ],
        ]);
    }
}
