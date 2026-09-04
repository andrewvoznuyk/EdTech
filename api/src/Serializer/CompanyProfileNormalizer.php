<?php

namespace App\Serializer;

use App\Entity\CompanyProfile;
use ArrayObject;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CompanyProfileNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'COMPANY_PROFILE_NORMALIZER_ALREADY_CALLED';

    /**
     * @param mixed $data
     * @param string|null $format
     * @param array $context
     * @return array|string|int|float|bool|ArrayObject|null
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($data, $format, $context);

        if (!empty($data['rnokpp'])) {
            unset($data['edrpou']);
        }

        if (!empty($data['edrpou'])) {
            unset($data['rnokpp']);
        }

        return $data;
    }

    /**
     * @param mixed $data
     * @param string|null $format
     * @param array $context
     * @return bool
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CompanyProfile && !isset($context[self::ALREADY_CALLED]);
    }

    /**
     * @param string|null $format
     * @return false[]
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            CompanyProfile::class => false,
        ];
    }
}
