<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\ConfigurableOfferProvider;

class ConfigurableOfferProviderTest extends AbstractProviderTestCase
{
    private const FLAG = 'panth_structured_data/structured_data/configurable_multi_offer';

    private function stockRegistry(array $inStockIds): StockRegistryInterface
    {
        $registry = $this->createStub(StockRegistryInterface::class);
        $registry->method('getStockItem')->willReturnCallback(
            function (int $id) use ($inStockIds): StockItemInterface {
                if ($id === 99) {
                    throw new \RuntimeException('stock');
                }
                $item = $this->createStub(StockItemInterface::class);
                $item->method('getIsInStock')->willReturn(in_array($id, $inStockIds, true));
                return $item;
            }
        );

        return $registry;
    }

    private function provider(
        ?Product $product,
        mixed $children = [],
        array $flags = [],
        ?StoreManagerInterface $storeManager = null
    ): ConfigurableOfferProvider {
        $type = $this->createStub(Configurable::class);
        if ($children instanceof \Throwable) {
            $type->method('getUsedProducts')->willThrowException($children);
        } else {
            $type->method('getUsedProducts')->willReturn($children);
        }

        return new ConfigurableOfferProvider(
            $this->registry($product !== null ? ['current_product' => $product] : []),
            $this->request(),
            $storeManager ?? $this->storeManager($this->store(1, self::BASE, 'Store', 'GBP')),
            $this->config([], $flags),
            $type,
            $this->stockRegistry([10, 12])
        );
    }

    private function parent(): Product
    {
        return $this->product([
            'getTypeId' => 'configurable',
            'getName' => 'Tee',
            'getSku' => 'TEE',
            'getProductUrl' => 'https://example.com/tee.html',
        ]);
    }

    private function child(int $id, mixed $price, int $status = 1, int $visibility = 1, string $url = ''): Product
    {
        $methods = [
            'getId' => $id,
            'getStatus' => $status,
            'getFinalPrice' => $price,
            'getSku' => 'TEE-' . $id,
            'getVisibility' => $visibility,
            'getProductUrl' => $url,
        ];
        if ($price === null) {
            $priceObject = $this->createStub(PriceInterface::class);
            $priceObject->method('getValue')->willReturn(9.0);
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
        $parent = $this->parent();
        $simple = $this->product(['getTypeId' => 'simple']);

        $this->assertSame('configurable_offer', $this->provider(null)->getCode());
        $this->assertFalse($this->provider(null, [], [self::FLAG => true])->isApplicable());
        $this->assertFalse($this->provider($simple, [], [self::FLAG => true])->isApplicable());
        $this->assertFalse($this->provider($parent)->isApplicable());
        $this->assertFalse(
            $this->provider($parent, [], [self::FLAG => true, Config::XML_SD_PRODUCT_GROUP => true])->isApplicable()
        );
        $this->assertTrue($this->provider($parent, [], [self::FLAG => true])->isApplicable());
    }

    public function testBuildsAggregateOfferFromEnabledPricedChildren(): void
    {
        $children = [
            $this->child(10, 20.0, 1, 4, 'https://example.com/tee-red.html'),
            $this->child(11, null, 1, 1),
            $this->child(12, 0.0),
            $this->child(13, 30.0, 2),
            $this->child(99, 15.0, 1, 4, ''),
        ];

        $node = $this->provider($this->parent(), $children)->getJsonLd();

        $this->assertSame('Product', $node['@type']);
        $this->assertSame('https://example.com/tee.html#product', $node['@id']);
        $this->assertSame('TEE', $node['sku']);
        $offers = $node['offers'];
        $this->assertSame('AggregateOffer', $offers['@type']);
        $this->assertSame('9.00', $offers['lowPrice']);
        $this->assertSame('20.00', $offers['highPrice']);
        $this->assertSame(3, $offers['offerCount']);
        $this->assertSame('GBP', $offers['priceCurrency']);
        $this->assertSame([
            '@type' => 'Offer',
            'price' => '20.00',
            'priceCurrency' => 'GBP',
            'availability' => 'https://schema.org/InStock',
            'sku' => 'TEE-10',
            'url' => 'https://example.com/tee-red.html',
        ], $offers['offers'][0]);
        $this->assertSame('https://example.com/tee.html', $offers['offers'][1]['url']);
        $this->assertSame('https://schema.org/OutOfStock', $offers['offers'][1]['availability']);
        $this->assertSame('https://example.com/tee.html', $offers['offers'][2]['url']);
        $this->assertSame('https://schema.org/OutOfStock', $offers['offers'][2]['availability']);
    }

    public function testNoUsableChildrenGivesNoNode(): void
    {
        $this->assertSame([], $this->provider(null)->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), [])->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), new \RuntimeException('type'))->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), [$this->child(5, 10.0, 2)])->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), [$this->child(5, false)])->getJsonLd());
    }

    public function testStoreFailureFallsBackToUsd(): void
    {
        $node = $this->provider(
            $this->parent(),
            [$this->child(10, 5.0)],
            [],
            $this->failingStoreManager()
        )->getJsonLd();

        $this->assertSame('USD', $node['offers']['priceCurrency']);
    }
}
