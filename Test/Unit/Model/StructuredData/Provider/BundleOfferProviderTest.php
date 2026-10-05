<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Bundle\Model\Product\Price as BundlePrice;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPriceInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\BundleOfferProvider;

class BundleOfferProviderTest extends AbstractProviderTestCase
{
    private const FLAG = 'panth_structured_data/structured_data/configurable_multi_offer';

    private function stockRegistry(?bool $inStock): StockRegistryInterface
    {
        $registry = $this->createStub(StockRegistryInterface::class);
        if ($inStock === null) {
            $registry->method('getStockItem')->willThrowException(new \RuntimeException('stock'));
        } else {
            $item = $this->createStub(StockItemInterface::class);
            $item->method('getIsInStock')->willReturn($inStock);
            $registry->method('getStockItem')->willReturn($item);
        }

        return $registry;
    }

    private function provider(
        ?Product $product,
        array $flags = [],
        ?bool $inStock = true,
        ?PriceCurrencyInterface $priceCurrency = null,
        ?StoreManagerInterface $storeManager = null
    ): BundleOfferProvider {
        return new BundleOfferProvider(
            $this->registry($product !== null ? ['current_product' => $product] : []),
            $this->request(),
            $storeManager ?? $this->storeManager($this->store(1, self::BASE, 'Store', 'EUR')),
            $this->config([], $flags),
            $this->stockRegistry($inStock),
            $priceCurrency
        );
    }

    private function priceInfo(?float $min, ?float $max, ?float $value = null): PriceInfo
    {
        $amount = function (?float $v): AmountInterface {
            $a = $this->createStub(AmountInterface::class);
            $a->method('getValue')->willReturn($v);
            return $a;
        };
        $final = $this->createStubForIntersectionOfInterfaces([PriceInterface::class, FinalPriceInterface::class]);
        $final->method('getMinimalPrice')->willReturn($amount($min));
        $final->method('getMaximalPrice')->willReturn($amount($max));
        $final->method('getValue')->willReturn($value);
        $priceInfo = $this->createStub(PriceInfo::class);
        $priceInfo->method('getPrice')->willReturn($final);

        return $priceInfo;
    }

    private function bundle(array $methods, int $priceType): Product
    {
        return $this->product(
            array_merge([
                'getTypeId' => 'bundle',
                'getId' => 3,
                'getName' => 'Kit',
                'getSku' => 'KIT',
                'getProductUrl' => 'https://example.com/kit.html',
            ], $methods),
            ['price_type' => $priceType]
        );
    }

    private function bundlePriceModel(float $min, float $max): BundlePrice
    {
        $model = $this->createStub(BundlePrice::class);
        $model->method('getTotalPrices')->willReturnCallback(
            static fn($product, $which) => $which === 'min' ? $min : $max
        );

        return $model;
    }

    public function testApplicability(): void
    {
        $simple = $this->product(['getTypeId' => 'simple']);
        $bundle = $this->bundle([], 0);

        $this->assertSame('bundle_offer', $this->provider(null)->getCode());
        $this->assertFalse($this->provider(null, [self::FLAG => true])->isApplicable());
        $this->assertFalse($this->provider($simple, [self::FLAG => true])->isApplicable());
        $this->assertFalse($this->provider($bundle)->isApplicable());
        $this->assertTrue($this->provider($bundle, [self::FLAG => true])->isApplicable());
        $this->assertSame([], $this->provider(null)->getJsonLd());
    }

    public function testFixedPriceBundleGetsSingleConvertedOffer(): void
    {
        $currency = $this->createStub(PriceCurrencyInterface::class);
        $currency->method('convert')->willReturnCallback(static fn($amount) => $amount * 2);

        $node = $this->provider(
            $this->bundle(['getFinalPrice' => 10.0], BundlePrice::PRICE_TYPE_FIXED),
            [],
            true,
            $currency
        )->getJsonLd();

        $this->assertSame([
            '@type' => 'Product',
            '@id' => 'https://example.com/kit.html#product',
            'name' => 'Kit',
            'sku' => 'KIT',
            'url' => 'https://example.com/kit.html',
            'offers' => [
                '@type' => 'Offer',
                'price' => '20.00',
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'url' => 'https://example.com/kit.html',
            ],
        ], $node);
    }

    public function testFixedPriceFallsBackToPriceInfoAndDropsZeroPrice(): void
    {
        $viaPriceInfo = $this->bundle(
            ['getFinalPrice' => null, 'getPriceInfo' => $this->priceInfo(null, null, 8.5)],
            BundlePrice::PRICE_TYPE_FIXED
        );
        $zero = $this->bundle(
            ['getFinalPrice' => false, 'getPriceInfo' => static fn() => throw new \RuntimeException('pi')],
            BundlePrice::PRICE_TYPE_FIXED
        );

        $this->assertSame('8.50', $this->provider($viaPriceInfo)->getJsonLd()['offers']['price']);
        $this->assertSame([], $this->provider($zero)->getJsonLd());
    }

    public function testFailingCurrencyConversionKeepsOriginalAmount(): void
    {
        $throwing = $this->createStub(PriceCurrencyInterface::class);
        $throwing->method('convert')->willThrowException(new \RuntimeException('rate'));
        $nonNumeric = $this->createStub(PriceCurrencyInterface::class);
        $nonNumeric->method('convert')->willReturn('n/a');
        $bundle = $this->bundle(['getFinalPrice' => 12.0], BundlePrice::PRICE_TYPE_FIXED);

        $this->assertSame('12.00', $this->provider($bundle, [], true, $throwing)->getJsonLd()['offers']['price']);
        $this->assertSame('12.00', $this->provider($bundle, [], true, $nonNumeric)->getJsonLd()['offers']['price']);
    }

    public function testDynamicBundleUsesPriceModelRange(): void
    {
        $node = $this->provider(
            $this->bundle(['getPriceModel' => $this->bundlePriceModel(5.0, 15.0)], 0),
            [],
            false,
            null,
            $this->failingStoreManager()
        )->getJsonLd();

        $this->assertSame([
            '@type' => 'AggregateOffer',
            'lowPrice' => '5.00',
            'highPrice' => '15.00',
            'offerCount' => 1,
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/OutOfStock',
        ], $node['offers']);
    }

    public function testDynamicBundleFallsBackToPriceInfoRange(): void
    {
        $node = $this->provider($this->bundle([
            'getPriceModel' => $this->bundlePriceModel(0.0, 0.0),
            'getPriceInfo' => $this->priceInfo(0.0, 12.0),
        ], 0), [], null)->getJsonLd();

        $this->assertSame('12.00', $node['offers']['lowPrice']);
        $this->assertSame('12.00', $node['offers']['highPrice']);
        $this->assertSame('https://schema.org/OutOfStock', $node['offers']['availability']);
    }

    public function testDynamicBundleMaxFallsBackToMin(): void
    {
        $node = $this->provider($this->bundle([
            'getPriceModel' => $this->bundlePriceModel(4.0, 0.0),
        ], 0))->getJsonLd();

        $this->assertSame('4.00', $node['offers']['lowPrice']);
        $this->assertSame('4.00', $node['offers']['highPrice']);
    }

    public function testDynamicBundleFallsBackToFinalPriceThenGivesUp(): void
    {
        $throws = static fn() => throw new \RuntimeException('no');
        $withFinal = $this->bundle([
            'getPriceModel' => $throws,
            'getPriceInfo' => $throws,
            'getFinalPrice' => 7.0,
        ], 0);
        $zero = $this->bundle([
            'getPriceModel' => null,
            'getPriceInfo' => $this->priceInfo(0.0, 0.0),
            'getFinalPrice' => 0.0,
        ], 0);

        $node = $this->provider($withFinal)->getJsonLd();

        $this->assertSame('7.00', $node['offers']['lowPrice']);
        $this->assertSame('7.00', $node['offers']['highPrice']);
        $this->assertSame([], $this->provider($zero)->getJsonLd());
    }
}
