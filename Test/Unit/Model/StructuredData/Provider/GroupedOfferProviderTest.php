<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\GroupedOfferProvider;

class GroupedOfferProviderTest extends AbstractProviderTestCase
{
    private const FLAG = 'panth_structured_data/structured_data/configurable_multi_offer';

    private function provider(
        ?Product $product,
        mixed $children = [],
        array $flags = [],
        ?StoreManagerInterface $storeManager = null,
        ?PriceCurrencyInterface $priceCurrency = null
    ): GroupedOfferProvider {
        $type = $this->createStub(Grouped::class);
        if ($children instanceof \Throwable) {
            $type->method('getAssociatedProducts')->willThrowException($children);
        } else {
            $type->method('getAssociatedProducts')->willReturn($children);
        }
        $stock = $this->createStub(StockRegistryInterface::class);
        $stock->method('getStockItem')->willReturnCallback(
            function (int $id): StockItemInterface {
                if ($id === 99) {
                    throw new \RuntimeException('stock');
                }
                $item = $this->createStub(StockItemInterface::class);
                $item->method('getIsInStock')->willReturn($id % 2 === 0);
                return $item;
            }
        );

        return new GroupedOfferProvider(
            $this->registry($product !== null ? ['current_product' => $product] : []),
            $this->request(),
            $storeManager ?? $this->storeManager(),
            $this->config([], $flags),
            $type,
            $stock,
            $priceCurrency
        );
    }

    private function parent(): Product
    {
        return $this->product([
            'getTypeId' => 'grouped',
            'getName' => 'Set',
            'getSku' => 'SET',
            'getProductUrl' => 'https://example.com/set.html',
        ]);
    }

    private function child(int $id, mixed $price, int $status = 1, int $visibility = 4, string $url = ''): Product
    {
        $methods = [
            'getId' => $id,
            'getStatus' => $status,
            'getFinalPrice' => $price,
            'getSku' => 'SET-' . $id,
            'getName' => 'Part ' . $id,
            'getVisibility' => $visibility,
            'getProductUrl' => $url,
        ];
        if ($price === null) {
            $priceObject = $this->createStub(PriceInterface::class);
            $priceObject->method('getValue')->willReturn(3.25);
            $priceInfo = $this->createStub(PriceInfo::class);
            $priceInfo->method('getPrice')->willReturn($priceObject);
            $methods['getPriceInfo'] = $priceInfo;
        } elseif ($price === false) {
            $methods['getPriceInfo'] = static fn() => throw new \RuntimeException('no price info');
        }

        return $this->product($methods);
    }

    public function testApplicability(): void
    {
        $this->assertSame('grouped_offer', $this->provider(null)->getCode());
        $this->assertFalse($this->provider(null, [], [self::FLAG => true])->isApplicable());
        $this->assertFalse(
            $this->provider($this->product(['getTypeId' => 'simple']), [], [self::FLAG => true])->isApplicable()
        );
        $this->assertFalse($this->provider($this->parent())->isApplicable());
        $this->assertTrue($this->provider($this->parent(), [], [self::FLAG => true])->isApplicable());
    }

    public function testBuildsAggregateOfferWithNamedChildOffers(): void
    {
        $node = $this->provider($this->parent(), [
            $this->child(2, 10.0, 1, 4, 'https://example.com/part-2.html'),
            $this->child(3, null, 1, 0),
            $this->child(4, 50.0, 2),
            $this->child(99, false),
        ])->getJsonLd();

        $offers = $node['offers'];
        $this->assertSame('https://example.com/set.html#product', $node['@id']);
        $this->assertSame('SET', $node['sku']);
        $this->assertSame('3.25', $offers['lowPrice']);
        $this->assertSame('10.00', $offers['highPrice']);
        $this->assertSame(2, $offers['offerCount']);
        $this->assertSame('USD', $offers['priceCurrency']);
        $this->assertSame([
            '@type' => 'Offer',
            'price' => '10.00',
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
            'sku' => 'SET-2',
            'name' => 'Part 2',
            'url' => 'https://example.com/part-2.html',
        ], $offers['offers'][0]);
        $this->assertSame('https://schema.org/OutOfStock', $offers['offers'][1]['availability']);
        $this->assertSame('https://example.com/set.html', $offers['offers'][1]['url']);
    }

    public function testCurrencyConversionAndStoreFallback(): void
    {
        $currency = $this->createStub(PriceCurrencyInterface::class);
        $currency->method('convert')->willReturnCallback(static fn($amount) => $amount / 2);

        $node = $this->provider(
            $this->parent(),
            [$this->child(2, 10.0)],
            [],
            $this->failingStoreManager(),
            $currency
        )->getJsonLd();

        $this->assertSame('5.00', $node['offers']['lowPrice']);
        $this->assertSame('USD', $node['offers']['priceCurrency']);
    }

    public function testNoUsableChildrenGivesNoNode(): void
    {
        $this->assertSame([], $this->provider(null)->getJsonLd());
        $this->assertSame([], $this->provider($this->parent())->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), new \RuntimeException('x'))->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), [$this->child(2, 0.0)])->getJsonLd());
    }
}
