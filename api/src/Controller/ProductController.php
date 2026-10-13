<?php

namespace App\Controller;


use App\DTO\Common\PaginatedResult;
use App\DTO\Product\Request\ProductQueryDto;
use App\Entity\Product;
use App\Service\EntityService\Product\ProductServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param ProductServiceInterface $productService
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductServiceInterface $productService,
    ) {
    }

    /**
     * @param ProductQueryDto|null $filterDto
     * @return Response
     */
    #[Route('', name: 'get_collection_product', methods: ['GET'])]
    public function getCollectionProduct(#[MapQueryString] ?ProductQueryDto $filterDto = null): Response
    {
        $result = $this->productService->getCollection($filterDto ?? new ProductQueryDto());

        return $this->json($result, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [
                Product::GET_COLLECTION_PRODUCT, PaginatedResult::GET_COLLECTION_PAGINATION,
            ],
        ]);
    }

    /**
     * @param Product $product
     * @return Response
     */
    #[Route('/{id}', name: 'get_item_product', methods: ['GET'])]
    public function getProduct(Product $product): Response
    {
        return $this->json($product, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [Product::GET_ITEM_PRODUCT],
        ]);
    }

    /**
     * @param Product $product
     * @return JsonResponse
     */
    #[IsGranted("ROLE_ADMIN")]
    #[Route('', name: 'post_collection_product', methods: ['POST'])]
    public function createProduct(#[MapRequestPayload(serializationContext: ['groups' => [Product::POST_COLLECTION_PRODUCT]])] Product $product): JsonResponse
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json($product, Response::HTTP_CREATED, [], [
            AbstractNormalizer::GROUPS => [Product::GET_ITEM_PRODUCT],
        ]);
    }

    /**
     * @param Product $product
     * @param Product $updatedProduct
     * @return JsonResponse
     */
    #[IsGranted("ROLE_ADMIN")]
    #[Route('/{id}', name: 'patch_item_product', methods: ['PATCH'])]
    public function updateProduct(Product $product, #[MapRequestPayload(serializationContext: ['groups' => [Product::PATCH_ITEM_PRODUCT]])] Product $updatedProduct): JsonResponse
    {
        if ($updatedProduct->getTitle() !== null) {
            $product->setTitle($updatedProduct->getTitle());
        }
        if ($updatedProduct->getPrice() !== null) {
            $product->setPrice($updatedProduct->getPrice());
        }
        if ($updatedProduct->getAssetType() !== null) {
            $product->setAssetType($updatedProduct->getAssetType());
        }
        if ($updatedProduct->getDurationDays() !== null) {
            $product->setDurationDays($updatedProduct->getDurationDays());
        }
        if ($updatedProduct->getOptions() !== null) {
            $product->setOptions($updatedProduct->getOptions());
        }

        $this->entityManager->flush();

        return $this->json($product, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => [Product::GET_ITEM_PRODUCT],
        ]);
    }

    #[IsGranted("ROLE_ADMIN")]
    #[Route('/{id}', name: 'delete_item_product', methods: ['DELETE'])]
    public function deleteProduct(Product $product): Response
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
