<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Panth\StructuredData\Api\StructuredDataProviderInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Aggregator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AggregatorTest extends TestCase
{
    private const PRODUCT_ID = 'https://example.com/app.html#product';

    private function provider(string $code, array $node): StructuredDataProviderInterface
    {
        $provider = $this->createStub(StructuredDataProviderInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('isApplicable')->willReturn(true);
        $provider->method('getJsonLd')->willReturn($node);

        return $provider;
    }

    private function build(array $providers): array
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isStructuredDataEnabled')->willReturn(true);
        $config->method('isDebug')->willReturn(false);

        $aggregator = new Aggregator($providers, $config, $this->createStub(LoggerInterface::class));

        return json_decode($aggregator->build(), true);
    }

    private function nodeById(array $document, string $id): array
    {
        foreach ($document['@graph'] as $node) {
            if (($node['@id'] ?? '') === $id) {
                return $node;
            }
        }
        $this->fail('Node ' . $id . ' not found');
    }

    public function testSoftwareNodeKeepsItsTypeAndReviewPointsToIt(): void
    {
        $document = $this->build([
            'product' => $this->provider('product', [
                '@type' => 'SoftwareApplication',
                '@id' => self::PRODUCT_ID,
                'name' => 'App',
                'offers' => ['@type' => 'Offer', 'price' => '0.00'],
            ]),
            'customProperties' => $this->provider('customProperties', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'award' => 'Best App',
            ]),
            'review' => $this->provider('review', [[
                '@type' => 'Review',
                '@id' => 'https://example.com/app.html#review-1',
                'itemReviewed' => ['@id' => self::PRODUCT_ID],
            ]]),
        ]);

        $software = $this->nodeById($document, self::PRODUCT_ID);
        $review = $this->nodeById($document, 'https://example.com/app.html#review-1');

        $this->assertCount(2, $document['@graph']);
        $this->assertSame('SoftwareApplication', $software['@type']);
        $this->assertSame('Best App', $software['award']);
        $this->assertSame('0.00', $software['offers']['price']);
        $this->assertSame($software['@id'], $review['itemReviewed']['@id']);
    }

    public function testProductNodeTypeStillFollowsMerges(): void
    {
        $document = $this->build([
            'product' => $this->provider('product', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'name' => 'Bag',
                'offers' => ['@type' => 'Offer'],
            ]),
            'configurableOffer' => $this->provider('configurableOffer', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'offers' => ['@type' => 'AggregateOffer', 'lowPrice' => '10.00', 'offerCount' => 2],
            ]),
            'website' => $this->provider('website', [
                '@type' => 'WebSite',
                '@id' => 'https://example.com/#website',
                'name' => 'Shop',
            ]),
        ]);

        $product = $this->nodeById($document, self::PRODUCT_ID);

        $this->assertSame('Product', $product['@type']);
        $this->assertSame('AggregateOffer', $product['offers']['@type']);
    }

    public function testStandalonePricelessOfferNodeIsDropped(): void
    {
        $document = $this->build([
            'product' => $this->provider('product', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'name' => 'Bag',
                'offers' => ['@type' => 'Offer', 'price' => '25.00', 'priceCurrency' => 'USD'],
            ]),
            'shipping' => $this->provider('shipping', [
                '@type' => 'Offer',
                '@id' => 'https://example.com/app.html#offer-shipping',
                'shippingDetails' => ['@type' => 'OfferShippingDetails'],
            ]),
            'website' => $this->provider('website', [
                '@type' => 'WebSite',
                '@id' => 'https://example.com/#website',
                'name' => 'Shop',
            ]),
        ]);

        $this->assertSame(['Product', 'WebSite'], array_column($document['@graph'], '@type'));
    }

    public function testPricelessOffersAreRemovedFromProductNode(): void
    {
        $document = $this->build([
            'product' => $this->provider('product', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'name' => 'Bag',
                'offers' => ['@type' => 'Offer', 'itemCondition' => 'https://schema.org/NewCondition'],
            ]),
            'paymentMethod' => $this->provider('paymentMethod', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'offers' => ['acceptedPaymentMethod' => ['http://purl.org/goodrelations/v1#PayPal']],
            ]),
            'website' => $this->provider('website', [
                '@type' => 'WebSite',
                '@id' => 'https://example.com/#website',
                'name' => 'Shop',
            ]),
        ]);

        $product = $this->nodeById($document, self::PRODUCT_ID);

        $this->assertArrayNotHasKey('offers', $product);
    }

    public function testLinkedOfferPropertiesMergeIntoThePricedOffer(): void
    {
        $document = $this->build([
            'product' => $this->provider('product', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'name' => 'Bag',
                'offers' => ['@type' => 'Offer', 'price' => '25.00', 'priceCurrency' => 'USD'],
            ]),
            'paymentMethod' => $this->provider('paymentMethod', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'offers' => ['acceptedPaymentMethod' => [['@type' => 'CreditCard', 'name' => 'Credit Card']]],
            ]),
            'website' => $this->provider('website', [
                '@type' => 'WebSite',
                '@id' => 'https://example.com/#website',
                'name' => 'Shop',
            ]),
        ]);

        $product = $this->nodeById($document, self::PRODUCT_ID);

        $this->assertCount(2, $document['@graph']);
        $this->assertSame('25.00', $product['offers']['price']);
        $this->assertSame('CreditCard', $product['offers']['acceptedPaymentMethod'][0]['@type']);
    }

    public function testNodesWithoutIdAreNotMergedByType(): void
    {
        $document = $this->build([
            'first' => $this->provider('first', ['@type' => 'SaleEvent', 'name' => 'Spring Sale']),
            'second' => $this->provider('second', ['@type' => 'SaleEvent', 'name' => 'Autumn Sale']),
        ]);

        $this->assertSame(['Spring Sale', 'Autumn Sale'], array_column($document['@graph'], 'name'));
    }

    public function testProductGroupKeepsItsTypeAndVariantOffers(): void
    {
        $document = $this->build([
            'product' => $this->provider('product', [
                '@type' => 'ProductGroup',
                '@id' => self::PRODUCT_ID,
                'name' => 'Hoodie',
                'productGroupID' => 'MH01',
            ]),
            'productGroup' => $this->provider('product_group', [
                '@type' => 'ProductGroup',
                '@id' => self::PRODUCT_ID,
                'hasVariant' => [
                    [
                        '@type' => 'Product',
                        'sku' => 'MH01-S',
                        'offers' => ['@type' => 'Offer', 'price' => '52.00', 'priceCurrency' => 'USD'],
                    ],
                    [
                        '@type' => 'Product',
                        'sku' => 'MH01-M',
                        'offers' => ['@type' => 'Offer'],
                    ],
                ],
            ]),
            'review' => $this->provider('review', [
                '@type' => 'Product',
                '@id' => self::PRODUCT_ID,
                'award' => 'Warmest',
            ]),
        ]);

        $this->assertSame('ProductGroup', $document['@type']);
        $this->assertSame('Warmest', $document['award']);
        $this->assertSame('52.00', $document['hasVariant'][0]['offers']['price']);
        $this->assertArrayNotHasKey('offers', $document['hasVariant'][1]);
        $this->assertArrayNotHasKey('offers', $document);
    }
}
