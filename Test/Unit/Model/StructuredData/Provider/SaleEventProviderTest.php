<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\SaleEventProvider;
use PHPUnit\Framework\TestCase;

class SaleEventProviderTest extends TestCase
{
    private function provider(string $typeId, float $finalPrice): SaleEventProvider
    {
        $data = [
            'special_price' => '25.00',
            'special_from_date' => '2020-01-01 00:00:00',
            'special_to_date' => null,
        ];
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getProductUrl')->willReturn('https://example.com/tote.html');
        $product->method('getData')->willReturnCallback(
            static fn ($key = '', $index = null) => $data[$key] ?? null
        );

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnMap([
            ['current_product', $product],
        ]);

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->willReturnArgument(0);

        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(static fn () => new \DateTime('2026-09-30 12:00:00'));

        return new SaleEventProvider(
            $registry,
            $this->createStub(RequestInterface::class),
            $storeManager,
            $this->createStub(Config::class),
            $scopeConfig,
            $priceCurrency,
            $timezone
        );
    }

    public function testSpecialPriceIsAttachedToTheProductOffer(): void
    {
        $provider = $this->provider('simple', 25.0);
        $node = $provider->getJsonLd();

        $this->assertTrue($provider->isApplicable());
        $this->assertSame('Product', $node['@type']);
        $this->assertSame('https://example.com/tote.html#product', $node['@id']);
        $this->assertArrayNotHasKey('@type', $node['offers']);
        $this->assertSame('25.00', $node['offers']['priceSpecification']['price']);
        $this->assertSame('USD', $node['offers']['priceSpecification']['priceCurrency']);
    }

    public function testSkippedWhenFinalPriceDiffersFromSpecialPrice(): void
    {
        $this->assertSame([], $this->provider('simple', 22.5)->getJsonLd());
    }

    public function testSkippedForAggregatePriceTypes(): void
    {
        foreach (['configurable', 'bundle', 'grouped'] as $typeId) {
            $provider = $this->provider($typeId, 25.0);
            $this->assertFalse($provider->isApplicable(), $typeId);
            $this->assertSame([], $provider->getJsonLd(), $typeId);
        }
    }
}
