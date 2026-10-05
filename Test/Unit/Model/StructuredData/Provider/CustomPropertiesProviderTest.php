<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\CustomPropertiesProvider;
use Psr\Log\LoggerInterface;

class CustomPropertiesProviderTest extends AbstractProviderTestCase
{
    private function provider(array $registry, mixed $raw, ?LoggerInterface $logger = null): CustomPropertiesProvider
    {
        return new CustomPropertiesProvider(
            $this->registry($registry),
            $this->request(),
            $this->storeManager(),
            $this->config([Config::XML_SD_CUSTOM_PROPERTIES => $raw]),
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    private function productWithUrl(): array
    {
        return ['current_product' => $this->product(['getProductUrl' => 'https://example.com/p.html'])];
    }

    public function testApplicabilityFollowsCurrentProduct(): void
    {
        $this->assertSame('custom_properties', $this->provider([], '')->getCode());
        $this->assertFalse($this->provider([], '{"a":1}')->isApplicable());
        $this->assertTrue($this->provider($this->productWithUrl(), '{"a":1}')->isApplicable());
    }

    public function testNoProductGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([], '{"award":"x"}')->getJsonLd());
    }

    public function testBlankConfigGivesNoNode(): void
    {
        $this->assertSame([], $this->provider($this->productWithUrl(), '   ')->getJsonLd());
        $this->assertSame([], $this->provider($this->productWithUrl(), null)->getJsonLd());
    }

    public function testValidJsonIsMergedOntoProductNode(): void
    {
        $node = $this->provider($this->productWithUrl(), '{"award":"Best","material":"Wool"}')->getJsonLd();

        $this->assertSame([
            '@type' => 'Product',
            '@id' => 'https://example.com/p.html#product',
            'award' => 'Best',
            'material' => 'Wool',
        ], $node);
    }

    public function testDecodedKeysMayOverrideType(): void
    {
        $node = $this->provider($this->productWithUrl(), '{"@type":"Thing"}')->getJsonLd();

        $this->assertSame('Thing', $node['@type']);
    }

    public function testInvalidJsonLogsWarningAndGivesNoNode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Invalid JSON in custom_properties'));

        $this->assertSame([], $this->provider($this->productWithUrl(), '{broken', $logger)->getJsonLd());
    }

    public function testScalarOrEmptyJsonGivesNoNode(): void
    {
        $this->assertSame([], $this->provider($this->productWithUrl(), '42')->getJsonLd());
        $this->assertSame([], $this->provider($this->productWithUrl(), '[]')->getJsonLd());
    }
}
