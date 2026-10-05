<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\BrandProvider;

class BrandProviderTest extends AbstractProviderTestCase
{
    private function provider(array $registry, array $values = []): BrandProvider
    {
        return new BrandProvider(
            $this->registry($registry),
            $this->request(),
            $this->storeManager(),
            $this->config($values),
            $this->scopeConfig($values)
        );
    }

    public function testCodeAndDefaultApplicability(): void
    {
        $provider = $this->provider([]);

        $this->assertSame('brand', $provider->getCode());
        $this->assertTrue($provider->isApplicable());
    }

    public function testNoProductGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([])->getJsonLd());
    }

    public function testBrandTextComesFromConfiguredAttribute(): void
    {
        $product = $this->product(
            ['getAttributeText' => static fn(string $code) => $code === 'brand_code' ? 'Acme' : false],
            ['brand_code' => '12']
        );

        $node = $this->provider(
            ['current_product' => $product],
            [Config::XML_SD_BRAND_ATTRIBUTE => 'brand_code']
        )->getJsonLd();

        $this->assertSame(['@type' => 'Brand', 'name' => 'Acme'], $node);
    }

    public function testFallsBackToDefaultBrandWhenAttributeTextIsNotAString(): void
    {
        $product = $this->product(['getAttributeText' => false], ['manufacturer' => '5']);

        $node = $this->provider(
            ['current_product' => $product],
            [Config::XML_SD_DEFAULT_BRAND => '  House Brand  ']
        )->getJsonLd();

        $this->assertSame('House Brand', $node['name']);
    }

    public function testEmptyBrandEverywhereGivesNoNode(): void
    {
        $product = $this->product();

        $this->assertSame([], $this->provider(['current_product' => $product])->getJsonLd());
    }
}
