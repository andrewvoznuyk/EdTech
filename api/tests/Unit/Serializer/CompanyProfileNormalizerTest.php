<?php

namespace App\Tests\Unit\Serializer;

use App\Entity\CompanyProfile;
use App\Serializer\CompanyProfileNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CompanyProfileNormalizerTest extends TestCase
{
    /**
     * @return void
     */
    public function testSupportsNormalization(): void
    {
        $normalizer = new CompanyProfileNormalizer();

        $profile = new CompanyProfile();
        $this->assertTrue($normalizer->supportsNormalization($profile));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
        $this->assertFalse($normalizer->supportsNormalization($profile, null, ['COMPANY_PROFILE_NORMALIZER_ALREADY_CALLED' => true]));
        $this->assertSame([CompanyProfile::class => false], $normalizer->getSupportedTypes(null));
    }

    /**
     * @return void
     */
    public function testNormalizeUnsetsEdrpouWhenRnokppPresent(): void
    {
        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $profile = new CompanyProfile();

        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn([
                'companyName' => 'FOP Franko',
                'rnokpp' => '1234567890',
                'edrpou' => null,
            ]);

        $normalizer = new CompanyProfileNormalizer();
        $normalizer->setNormalizer($innerNormalizer);

        $result = $normalizer->normalize($profile);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rnokpp', $result);
        $this->assertArrayNotHasKey('edrpou', $result);
    }

    /**
     * @return void
     */
    public function testNormalizeUnsetsRnokppWhenEdrpouPresent(): void
    {
        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $profile = new CompanyProfile();

        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn([
                'companyName' => 'LLC Perevireno',
                'edrpou' => '12345678',
                'rnokpp' => null,
            ]);

        $normalizer = new CompanyProfileNormalizer();
        $normalizer->setNormalizer($innerNormalizer);

        $result = $normalizer->normalize($profile);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('edrpou', $result);
        $this->assertArrayNotHasKey('rnokpp', $result);
    }
}
