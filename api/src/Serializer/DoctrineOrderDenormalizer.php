<?php

namespace App\Serializer;

use Doctrine\Common\Collections\Order;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DoctrineOrderDenormalizer implements DenormalizerInterface
{
    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @param array<string, mixed> $context
     * @return Order
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Order
    {
        if ($data instanceof Order) {
            return $data;
        }

        if (is_string($data)) {
            $normalizedValue = strtoupper(trim($data));
            if ($normalizedValue === 'ASC' || $normalizedValue === 'ASCENDING') {
                return Order::Ascending;
            }
            if ($normalizedValue === 'DESC' || $normalizedValue === 'DESCENDING') {
                return Order::Descending;
            }
        }

        return Order::Descending;
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
        return $type === Order::class;
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
