<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\ProductProvider;
use Panth\StructuredData\Model\StructuredData\Shipping\ShippingDetailsBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class ProductProviderCoverageTest extends AbstractProviderTestCase
{
    private const THRESHOLD = 'panth_structured_data/structured_data/limited_stock_threshold';

    private function provider(
        Product $product,
        array $values = [],
        array $flags = [],
        mixed $stock = false,
        ?StoreManagerInterface $storeManager = null,
        bool $withScopeConfig = true,
        ?ImageHelper $imageHelper = null,
        bool $withShipping = false
    ): ProductProvider {
        $stockRegistry = null;
        if ($stock instanceof \Throwable) {
            $stockRegistry = $this->createStub(StockRegistryInterface::class);
            $stockRegistry->method('getStockItem')->willThrowException($stock);
        } elseif (is_array($stock)) {
            $item = $this->createStub(StockItemInterface::class);
            $item->method('getQty')->willReturn($stock['qty']);
            $item->method('getIsInStock')->willReturn($stock['in_stock']);
            $item->method('getBackorders')->willReturn($stock['backorders'] ?? 0);
            $stockRegistry = $this->createStub(StockRegistryInterface::class);
            $stockRegistry->method('getStockItem')->willReturn($item);
        }

        if ($imageHelper === null) {
            $imageHelper = $this->createStub(ImageHelper::class);
            $imageHelper->method('init')->willThrowException(new \RuntimeException('no image'));
        }

        $review = $this->createStub(Review::class);
        $reviewFactory = $this->createStub(ReviewFactory::class);
        $reviewFactory->method('create')->willReturn($review);

        $scopeConfig = $this->scopeConfig($values, $flags);
        $config = new Config($scopeConfig);

        return new ProductProvider(
            $this->registry(['current_product' => $product]),
            $this->request(),
            $storeManager ?? $this->storeManager($this->store(1, self::BASE, 'Store', 'EUR')),
            $config,
            $imageHelper,
            $this->createStub(PriceCurrencyInterface::class),
            $reviewFactory,
            $stockRegistry,
            $withScopeConfig ? $scopeConfig : null,
            $withShipping ? new ShippingDetailsBuilder($config, $scopeConfig) : null
        );
    }

    private function simple(array $methods = [], array $data = []): Product
    {
        return $this->product(array_merge([
            'getTypeId' => 'simple',
            'getId' => 5,
            'getName' => 'Lamp',
            'getSku' => 'LAMP',
            'getProductUrl' => 'https://example.com/lamp.html',
            'getFinalPrice' => 25.0,
            'getStatus' => 1,
            'getVisibility' => 4,
            'getAttributeText' => false,
            'isAvailable' => true,
            'isSalable' => true,
        ], $methods), $data);
    }

    public function testFullSimpleProductNode(): void
    {
        $imageHelper = $this->createStub(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('https://example.com/media/main.jpg');
        $gallery = new Collection($this->createStub(EntityFactoryInterface::class));
        $gallery->addItem(new DataObject(['url' => 'https://example.com/media/main.jpg']));
        $gallery->addItem(new DataObject(['url' => '']));
        $gallery->addItem(new DataObject(['url' => 'https://example.com/media/side.jpg']));

        $product = $this->simple(
            [
                'getMediaGalleryImages' => $gallery,
                'getAttributeText' => static fn(string $code) => $code === 'manufacturer' ? ['Acme', ' ', 'Co', []] : false,
                'getSpecialToDate' => date('Y-m-d', strtotime('+10 days')),
            ],
            [
                'short_description' => '<p>Bright <i>lamp</i></p>',
                'part_no' => 'MPN-1',
                'ean' => '4006381333931',
                'target_audience' => ' Adults ',
                'rating_summary' => new DataObject(['reviews_count' => 4, 'rating_summary' => 90]),
            ]
        );

        $node = $this->provider(
            $product,
            [
                Config::XML_SD_MPN_ATTRIBUTE => 'part_no',
                Config::XML_SD_DELIVERY_METHODS => "Standard|2|4|3.5\nExpress|1|1|9",
                self::THRESHOLD => '3',
            ],
            [],
            ['qty' => 2, 'in_stock' => true],
            null,
            true,
            $imageHelper,
            true
        )->getJsonLd();

        $this->assertSame('Product', $node['@type']);
        $this->assertSame('https://example.com/lamp.html#product', $node['@id']);
        $this->assertSame('LAMP', $node['sku']);
        $this->assertSame(['https://example.com/media/main.jpg', 'https://example.com/media/side.jpg'], $node['image']);
        $this->assertSame('Bright lamp', $node['description']);
        $this->assertSame('MPN-1', $node['mpn']);
        $this->assertSame('4006381333931', $node['gtin']);
        $this->assertSame(['@type' => 'Brand', 'name' => 'Acme, Co'], $node['brand']);
        $this->assertSame(['@type' => 'PeopleAudience', 'audienceType' => 'Adults'], $node['audience']);

        $offer = $node['offers'];
        $this->assertSame('25.00', $offer['price']);
        $this->assertSame('EUR', $offer['priceCurrency']);
        $this->assertSame('https://schema.org/LimitedAvailability', $offer['availability']);
        $this->assertSame(['@id' => 'https://example.com/#organization'], $offer['seller']);
        $this->assertSame(date('Y-m-d', strtotime('+10 days')), $offer['priceValidUntil']);
        $this->assertCount(2, $offer['shippingDetails']);
        $this->assertSame('Express', $offer['shippingDetails'][1]['shippingLabel']);

        $this->assertSame([
            '@type' => 'AggregateRating',
            'ratingValue' => '4.50',
            'bestRating' => '5',
            'worstRating' => '1',
            'reviewCount' => 4,
        ], $node['aggregateRating']);
    }

    public function testSingleImageAndSingleShippingEntryAreNotWrapped(): void
    {
        $imageHelper = $this->createStub(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('https://example.com/media/only.jpg');

        $node = $this->provider(
            $this->simple(['getMediaGalleryImages' => static fn() => throw new \RuntimeException('gallery')]),
            [Config::XML_SD_DELIVERY_METHODS => 'Standard|2|4|3.5'],
            [],
            false,
            null,
            true,
            $imageHelper,
            true
        )->getJsonLd();

        $this->assertSame('https://example.com/media/only.jpg', $node['image']);
        $this->assertSame('OfferShippingDetails', $node['offers']['shippingDetails']['@type']);
    }

    public function testGtinFromConfiguredAttributeAndDescriptionFallbacks(): void
    {
        $node = $this->provider(
            $this->simple([], ['barcode' => '123', 'gtin' => 'ignored', 'meta_description' => '', 'description' => 'Plain']),
            [Config::XML_SD_GTIN_ATTRIBUTE => 'barcode']
        )->getJsonLd();

        $this->assertSame('123', $node['gtin']);
        $this->assertSame('Plain', $node['description']);
        $this->assertArrayNotHasKey('mpn', $node);
        $this->assertArrayNotHasKey('aggregateRating', $node);
    }

    #[DataProvider('availabilityProvider')]
    public function testAvailabilityResolution(array $methods, array $data, mixed $stock, array $values, string $expected): void
    {
        $node = $this->provider($this->simple($methods, $data), $values, [], $stock)->getJsonLd();

        $this->assertSame('https://schema.org/' . $expected, $node['offers']['availability']);
    }

    public static function availabilityProvider(): array
    {
        $future = date('Y-m-d', strtotime('+30 days'));
        $past = date('Y-m-d', strtotime('-30 days'));

        return [
            'disabled' => [['getStatus' => 2], [], false, [], 'Discontinued'],
            'not visible' => [['getVisibility' => 1], [], false, [], 'Discontinued'],
            'future news date' => [[], ['news_from_date' => $future], false, [], 'PreOrder'],
            'past news date is ignored' => [[], ['news_from_date' => $past], false, [], 'InStock'],
            'out of stock with backorders' => [[], [], ['qty' => 0, 'in_stock' => false, 'backorders' => 1], [], 'BackOrder'],
            'in stock zero qty with backorders' => [[], [], ['qty' => 0, 'in_stock' => true, 'backorders' => 2], [], 'BackOrder'],
            'out of stock' => [[], [], ['qty' => 5, 'in_stock' => false], [], 'OutOfStock'],
            'default threshold' => [[], [], ['qty' => 4, 'in_stock' => true], [], 'LimitedAvailability'],
            'threshold floor of one' => [[], [], ['qty' => 4, 'in_stock' => true], [self::THRESHOLD => '0'], 'InStock'],
            'plenty in stock' => [[], [], ['qty' => 50, 'in_stock' => true], [], 'InStock'],
            'stock lookup error' => [[], [], new \RuntimeException('stock'), [], 'OutOfStock'],
            'no stock registry, unavailable' => [['isAvailable' => false], [], false, [], 'OutOfStock'],
        ];
    }

    public function testThresholdDefaultsWithoutScopeConfig(): void
    {
        $node = $this->provider(
            $this->simple(),
            [],
            [],
            ['qty' => 4, 'in_stock' => true],
            $this->failingStoreManager(),
            false
        )->getJsonLd();

        $this->assertSame('https://schema.org/LimitedAvailability', $node['offers']['availability']);
        $this->assertSame('USD', $node['offers']['priceCurrency']);
        $this->assertSame(['@id' => '/#organization'], $node['offers']['seller']);
    }

    public function testPriceValidUntilFallbacks(): void
    {
        $past = $this->simple(['getSpecialToDate' => date('Y-m-d', strtotime('-2 days'))]);

        $fromConfig = $this->provider($past, [Config::XML_SD_PRICE_VALID_UNTIL_DEFAULT => '2031-05-06'])->getJsonLd();
        $badConfig = $this->provider($past, [Config::XML_SD_PRICE_VALID_UNTIL_DEFAULT => 'not a date at all'])
            ->getJsonLd();
        $default = $this->provider($this->simple())->getJsonLd();

        $this->assertSame('2031-05-06', $fromConfig['offers']['priceValidUntil']);
        $this->assertSame(date('Y-m-d', strtotime('+1 year')), $badConfig['offers']['priceValidUntil']);
        $this->assertSame(date('Y-m-d', strtotime('+1 year')), $default['offers']['priceValidUntil']);
    }

    public function testPriceFallsBackToPriceInfoOrZero(): void
    {
        $priceObject = $this->createStub(PriceInterface::class);
        $priceObject->method('getValue')->willReturn(13.5);
        $priceInfo = $this->createStub(PriceInfo::class);
        $priceInfo->method('getPrice')->willReturn($priceObject);

        $viaInfo = $this->provider($this->simple(['getFinalPrice' => null, 'getPriceInfo' => $priceInfo]))
            ->getJsonLd();
        $zero = $this->provider($this->simple([
            'getFinalPrice' => false,
            'getPriceInfo' => static fn() => throw new \RuntimeException('pi'),
        ]))->getJsonLd();

        $this->assertSame('13.50', $viaInfo['offers']['price']);
        $this->assertSame('0.00', $zero['offers']['price']);
    }

    public function testConfigurableAsProductGroupHasNoOffer(): void
    {
        $node = $this->provider(
            $this->simple(['getTypeId' => 'configurable']),
            [],
            [Config::XML_SD_PRODUCT_GROUP => true]
        )->getJsonLd();

        $this->assertSame('ProductGroup', $node['@type']);
        $this->assertSame('LAMP', $node['productGroupID']);
        $this->assertArrayNotHasKey('offers', $node);
    }

    public function testVariantTypesGetOfferWithoutOwnPrice(): void
    {
        foreach (['configurable', 'bundle', 'grouped'] as $type) {
            $node = $this->provider($this->simple(['getTypeId' => $type]), [], [], false, $this->failingStoreManager())
                ->getJsonLd();

            $this->assertSame('Product', $node['@type'], $type);
            $this->assertArrayNotHasKey('price', $node['offers'], $type);
            $this->assertArrayNotHasKey('availability', $node['offers'], $type);
            $this->assertSame('https://schema.org/NewCondition', $node['offers']['itemCondition'], $type);
        }
    }

    public function testSoftwareNodeCarriesVersionPublisherAndDates(): void
    {
        $product = $this->simple([], [
            Config::SOFTWARE_ATTRIBUTE => '1',
            'panth_software_version' => ' 2.1.0 ',
            'brand' => 'Panth',
            'created_at' => '2023-01-01T00:00:00+00:00',
            'updated_at' => '2024-06-01T12:00:00+00:00',
        ]);

        $node = $this->provider($product, [], [Config::XML_SD_SOFTWARE_APPLICATION => true])->getJsonLd();

        $this->assertSame('SoftwareApplication', $node['@type']);
        $this->assertArrayNotHasKey('sku', $node);
        $this->assertSame('2.1.0', $node['softwareVersion']);
        $this->assertSame(['@type' => 'Organization', 'name' => 'Panth'], $node['publisher']);
        $this->assertSame('2024-06-01T12:00:00+00:00', $node['dateModified']);
        $this->assertSame('2023-01-01T00:00:00+00:00', $node['datePublished']);
        $this->assertSame([
            '@type' => 'Offer',
            'url' => 'https://example.com/lamp.html',
            'price' => '25.00',
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
        ], $node['offers']);
    }

    public function testSoftwareNodeSkipsUnparseableDates(): void
    {
        $product = $this->simple([], [Config::SOFTWARE_ATTRIBUTE => '1', 'updated_at' => 'garbage', 'created_at' => '']);

        $node = $this->provider($product, [], [Config::XML_SD_SOFTWARE_APPLICATION => true])->getJsonLd();

        $this->assertArrayNotHasKey('dateModified', $node);
        $this->assertArrayNotHasKey('datePublished', $node);
        $this->assertArrayNotHasKey('publisher', $node);
    }

    public function testRatingIgnoredWithoutReviewsOrSummaryObject(): void
    {
        $noReviews = $this->simple([], ['rating_summary' => new DataObject(['reviews_count' => 0, 'rating_summary' => 80])]);
        $scalar = $this->simple([], ['rating_summary' => 75]);

        $this->assertArrayNotHasKey('aggregateRating', $this->provider($noReviews)->getJsonLd());
        $this->assertArrayNotHasKey('aggregateRating', $this->provider($scalar)->getJsonLd());
    }

    public function testNonStringAttributeTextIsTreatedAsEmpty(): void
    {
        $product = $this->simple([
            'getAttributeText' => static fn(string $code) => $code === 'gender' ? 42 : null,
        ]);

        $node = $this->provider($product)->getJsonLd();

        $this->assertArrayNotHasKey('audience', $node);
        $this->assertArrayNotHasKey('brand', $node);
    }
}
