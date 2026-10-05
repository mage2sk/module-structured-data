<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Panth\StructuredData\Api\StructuredDataProviderInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Aggregator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AggregatorProductToggleTest extends TestCase
{
    private function provider(string $code, array $node): StructuredDataProviderInterface
    {
        $provider = $this->createStub(StructuredDataProviderInterface::class);
        $provider->method('isApplicable')->willReturn(true);
        $provider->method('getCode')->willReturn($code);
        $provider->method('getJsonLd')->willReturn($node);

        return $provider;
    }

    private function build(bool $productEnabled): array
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isStructuredDataEnabled')->willReturnCallback(
            static fn(string $code) => $code !== 'product' || $productEnabled
        );
        $url = 'https://example.com/tent.html';
        $providers = [
            'organization' => $this->provider('organization', [
                '@type' => 'Organization', '@id' => 'https://example.com/#organization', 'name' => 'Shop',
            ]),
            'return_policy' => $this->provider('return_policy', [
                '@type' => 'Product', '@id' => $url . '#product',
                'offers' => ['hasMerchantReturnPolicy' => ['@type' => 'MerchantReturnPolicy']],
            ]),
            'review' => $this->provider('review', [
                ['@type' => 'Product', '@id' => $url . '#product', 'aggregateRating' => ['ratingValue' => '4']],
                ['@type' => 'Review', '@id' => $url . '#review-1', 'itemReviewed' => ['@id' => $url . '#product']],
            ]),
            'productGroup' => $this->provider('productGroup', [
                '@type' => 'ProductGroup', '@id' => 'https://example.com/hoodie.html#product', 'name' => 'Hoodie',
            ]),
        ];
        $aggregator = new Aggregator($providers, $config, $this->createStub(LoggerInterface::class));
        $doc = json_decode($aggregator->build(), true);

        return array_column($doc['@graph'] ?? [$doc], '@type');
    }

    public function testProductFragmentsAndTheirReviewsAreDroppedWhenProductTypeIsOff(): void
    {
        $this->assertSame(['Organization', 'ProductGroup'], $this->build(false));
    }

    public function testProductFragmentsAreKeptWhenProductTypeIsOn(): void
    {
        $this->assertSame(['Organization', 'Product', 'Review', 'ProductGroup'], $this->build(true));
    }
}
