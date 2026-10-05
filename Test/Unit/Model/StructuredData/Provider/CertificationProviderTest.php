<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\CertificationProvider;

class CertificationProviderTest extends AbstractProviderTestCase
{
    private const ENABLED = 'panth_structured_data/structured_data/certification_enabled';
    private const ATTRIBUTE = 'panth_structured_data/structured_data/certification_attribute';

    private function provider(
        array $registry,
        array $values = [],
        array $flags = [],
        ?StoreManagerInterface $storeManager = null
    ): CertificationProvider {
        return new CertificationProvider(
            $this->registry($registry),
            $this->request(),
            $storeManager ?? $this->storeManager(),
            $this->config(),
            $this->scopeConfig($values, $flags)
        );
    }

    private function productWith(array $data): array
    {
        return ['current_product' => $this->product(['getProductUrl' => 'https://example.com/p.html'], $data)];
    }

    public function testApplicability(): void
    {
        $this->assertSame('certification_enabled', $this->provider([])->getCode());
        $this->assertFalse($this->provider([], [], [self::ENABLED => true])->isApplicable());
        $this->assertFalse($this->provider($this->productWith([]))->isApplicable());
        $this->assertTrue($this->provider($this->productWith([]), [], [self::ENABLED => true])->isApplicable());
        $this->assertTrue(
            $this->provider($this->productWith([]), [], [self::ENABLED => true], $this->failingStoreManager())
                ->isApplicable()
        );
    }

    public function testNoProductOrEmptyAttributeGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([])->getJsonLd());
        $this->assertSame([], $this->provider($this->productWith(['certifications' => '  ']))->getJsonLd());
        $this->assertSame(
            [],
            $this->provider($this->productWith(['certifications' => "# comment\nOnlyAuthority|"]))->getJsonLd()
        );
    }

    public function testSingleCertificationIsNotWrappedInList(): void
    {
        $node = $this->provider($this->productWith(['certifications' => 'EPREL | Energy Label | 123']))->getJsonLd();

        $this->assertSame('https://example.com/p.html#product', $node['@id']);
        $this->assertSame([
            '@type' => 'Certification',
            'certificationAuthority' => ['@type' => 'Organization', 'name' => 'EPREL'],
            'name' => 'Energy Label',
            'certificationIdentification' => '123',
        ], $node['hasCertification']);
    }

    public function testMultipleCertificationsFromConfiguredAttribute(): void
    {
        $node = $this->provider(
            $this->productWith(['certs' => "TUV|Safety\r\n\r\n# skip|me\nCE|Conformity|X1\n|NoAuthority"]),
            [self::ATTRIBUTE => 'certs'],
            [],
            $this->failingStoreManager()
        )->getJsonLd();

        $this->assertCount(2, $node['hasCertification']);
        $this->assertSame('Safety', $node['hasCertification'][0]['name']);
        $this->assertArrayNotHasKey('certificationIdentification', $node['hasCertification'][0]);
        $this->assertSame('X1', $node['hasCertification'][1]['certificationIdentification']);
    }
}
