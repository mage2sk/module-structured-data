<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\ProductGroupProvider;

class ProductGroupProviderTest extends AbstractProviderTestCase
{
    private const FLAG = 'panth_structured_data/structured_data/product_group_enabled';

    private function provider(
        ?Product $product,
        mixed $children = [],
        mixed $attributes = [],
        array $flags = [],
        ?StoreManagerInterface $storeManager = null
    ): ProductGroupProvider {
        $type = $this->createStub(Configurable::class);
        if ($children instanceof \Throwable) {
            $type->method('getUsedProducts')->willThrowException($children);
        } else {
            $type->method('getUsedProducts')->willReturn($children);
        }
        if ($attributes instanceof \Throwable) {
            $type->method('getConfigurableAttributesAsArray')->willThrowException($attributes);
        } else {
            $type->method('getConfigurableAttributesAsArray')->willReturn($attributes);
        }
        $stock = $this->createStub(StockRegistryInterface::class);
        $stock->method('getStockItem')->willReturnCallback(
            function (int $id): StockItemInterface {
                if ($id === 99) {
                    throw new \RuntimeException('stock');
                }
                $item = $this->createStub(StockItemInterface::class);
                $item->method('getIsInStock')->willReturn(true);
                return $item;
            }
        );

        return new ProductGroupProvider(
            $this->registry($product !== null ? ['current_product' => $product] : []),
            $this->request(),
            $storeManager ?? $this->storeManager($this->store(1, self::BASE, 'Store', 'EUR')),
            $this->config([Config::XML_SD_PRODUCT_CONDITION => 'used'], $flags),
            $type,
            $stock
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

    private function child(int $id, mixed $price, array $extra = [], array $data = []): Product
    {
        $methods = array_merge([
            'getId' => $id,
            'getStatus' => 1,
            'getFinalPrice' => $price,
            'getSku' => 'TEE-' . $id,
            'getName' => 'Tee ' . $id,
            'getVisibility' => 1,
            'getProductUrl' => '',
        ], $extra);
        if ($price === null) {
            $priceObject = $this->createStub(PriceInterface::class);
            $priceObject->method('getValue')->willReturn(11.0);
            $priceInfo = $this->createStub(PriceInfo::class);
            $priceInfo->method('getPrice')->willReturn($priceObject);
            $methods['getPriceInfo'] = $priceInfo;
        } elseif ($price === false) {
            $methods['getPriceInfo'] = static fn() => throw new \RuntimeException('pi');
        }

        return $this->product($methods, $data);
    }

    public function testApplicability(): void
    {
        $this->assertSame('product_group', $this->provider(null)->getCode());
        $this->assertFalse($this->provider(null, [], [], [self::FLAG => true])->isApplicable());
        $this->assertFalse(
            $this->provider($this->product(['getTypeId' => 'simple']), [], [], [self::FLAG => true])->isApplicable()
        );
        $this->assertFalse($this->provider($this->parent())->isApplicable());
        $this->assertTrue($this->provider($this->parent(), [], [], [self::FLAG => true])->isApplicable());
    }

    public function testBuildsProductGroupWithVariants(): void
    {
        $attributes = [
            ['attribute_code' => 'Color'],
            ['attribute_code' => 'size'],
            ['attribute_code' => 'fit'],
            ['label' => 'no code'],
        ];
        $children = [
            $this->child(
                10,
                20.0,
                [
                    'getVisibility' => 4,
                    'getProductUrl' => 'https://example.com/tee-red.html',
                    'getAttributeText' => static fn(string $code) => $code === 'Color' ? ' Red ' : ['S', 'M'],
                ],
                ['image' => '/r/e/red.jpg']
            ),
            $this->child(
                11,
                null,
                ['getAttributeText' => static fn() => throw new \RuntimeException('eav')],
                ['image' => 'no_selection', 'Color' => ' Blue ', 'size' => '']
            ),
            $this->child(12, 0.0),
            $this->child(13, 5.0, ['getStatus' => 2]),
        ];

        $node = $this->provider($this->parent(), $children, $attributes)->getJsonLd();

        $this->assertSame('ProductGroup', $node['@type']);
        $this->assertSame('https://example.com/tee.html#product', $node['@id']);
        $this->assertSame('TEE', $node['productGroupID']);
        $this->assertSame('Tee', $node['name']);
        $this->assertSame(['https://schema.org/color', 'https://schema.org/size'], $node['variesBy']);
        $this->assertCount(2, $node['hasVariant']);

        $red = $node['hasVariant'][0];
        $this->assertSame([
            '@type' => 'Product',
            'sku' => 'TEE-10',
            'name' => 'Tee 10',
            'url' => 'https://example.com/tee-red.html',
            'image' => 'https://example.com/media/catalog/product/r/e/red.jpg',
            'color' => 'Red',
            'size' => 'S, M',
            'isVariantOf' => ['@id' => 'https://example.com/tee.html#product'],
            'offers' => [
                '@type' => 'Offer',
                'price' => '20.00',
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'itemCondition' => 'https://schema.org/UsedCondition',
            ],
        ], $red);

        $blue = $node['hasVariant'][1];
        $this->assertSame('https://example.com/tee.html', $blue['url']);
        $this->assertArrayNotHasKey('image', $blue);
        $this->assertSame('Blue', $blue['color']);
        $this->assertArrayNotHasKey('size', $blue);
        $this->assertSame('11.00', $blue['offers']['price']);
    }

    public function testAttributeFailureOmitsVariesByAndStoreFailureDropsImage(): void
    {
        $store = $this->store(1, self::BASE, 'Store', 'EUR');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $calls = 0;
        $storeManager->method('getStore')->willReturnCallback(
            static function () use ($store, &$calls): Store {
                if (++$calls > 1) {
                    throw new \RuntimeException('store');
                }
                return $store;
            }
        );

        $node = $this->provider(
            $this->parent(),
            [$this->child(99, 4.0, [], ['image' => 'x.jpg'])],
            new \RuntimeException('attrs'),
            [],
            $storeManager
        )->getJsonLd();

        $this->assertArrayNotHasKey('variesBy', $node);
        $this->assertArrayNotHasKey('image', $node['hasVariant'][0]);
        $this->assertSame('https://schema.org/OutOfStock', $node['hasVariant'][0]['offers']['availability']);
    }

    public function testCurrencyFallsBackToUsd(): void
    {
        $node = $this->provider(
            $this->parent(),
            [$this->child(10, 4.0)],
            [],
            [],
            $this->failingStoreManager()
        )->getJsonLd();

        $this->assertSame('USD', $node['hasVariant'][0]['offers']['priceCurrency']);
    }

    public function testNoUsableVariantsGivesNoNode(): void
    {
        $this->assertSame([], $this->provider(null)->getJsonLd());
        $this->assertSame([], $this->provider($this->parent())->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), new \RuntimeException('x'))->getJsonLd());
        $this->assertSame([], $this->provider($this->parent(), [$this->child(1, false)])->getJsonLd());
    }
}
