<?php

namespace App\Serializer;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Uuid;

class OrderDenormalizer implements DenormalizerInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array<string, mixed> $context
     * @return Order|null
     * @throws NotFoundHttpException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ?Order
    {
        $idString = is_string($data) ? trim($data) : null;
        if ($idString === null) {
            return null;
        }

        if (str_contains($idString, '/')) {
            $idString = basename($idString);
        }

        if (!Uuid::isValid($idString)) {
            return null;
        }

        /** @var Order|null $order */
        $order = $this->entityManager->getRepository(Order::class)->findOneBy(['id' => $idString]);
        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        return $order;
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array<string, mixed> $context
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === Order::class && is_string($data);
    }

    /**
     * @param string|null $format
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Order::class => false,
        ];
    }
}
