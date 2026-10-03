<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\DB\Select;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\SaleEventProvider;

class SaleEventProviderCoverageTest extends AbstractProviderTestCase
{
    private const ENABLED = 'panth_structured_data/structured_data/sale_event_enabled';

    private function provider(
        array $registry,
        bool $enabled = true,
        ?PriceCurrencyInterface $priceCurrency = null,
        ?StoreManagerInterface $storeManager = null
    ): SaleEventProvider {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(static fn() => new \DateTime('2026-09-30 12:00:00'));

        return new SaleEventProvider(
            $this->registry($registry),
            $this->request(),
            $storeManager ?? $this->storeManager($this->store(1, self::BASE, 'Store', 'GBP')),
            $this->config(),
            $this->scopeConfig([], [self::ENABLED => $enabled]),
            $priceCurrency ?? $this->createStub(PriceCurrencyInterface::class),
            $timezone
        );
    }

    private function saleProduct(array $data, array $methods = []): \Magento\Catalog\Model\Product
    {
        return $this->product(array_merge([
            'getTypeId' => 'simple',
            'getFinalPrice' => (float) ($data['special_price'] ?? 0),
            'getProductUrl' => 'https://example.com/p.html',
        ], $methods), $data);
    }

    private function category(array $data, mixed $products = [], bool $urlFails = false): Category
    {
        $category = $this->createStub(Category::class);
        $category->method('getData')->willReturnCallback(static fn($key = '') => $data[$key] ?? null);
        $category->method('getName')->willReturn('Summer');
        if ($urlFails) {
            $category->method('getUrl')->willThrowException(new \RuntimeException('url'));
        } else {
            $category->method('getUrl')->willReturn('https://example.com/summer.html');
        }
        if ($products instanceof \Throwable) {
            $category->method('getProductCollection')->willThrowException($products);
        } else {
            $collection = $this->createStub(ProductCollection::class);
            $collection->method('getSelect')->willReturn($this->createStub(Select::class));
            $collection->method('getItems')->willReturn($products);
            $category->method('getProductCollection')->willReturn($collection);
        }

        return $category;
    }

    public function testCodeAndFeatureFlag(): void
    {
        $product = $this->saleProduct(['special_price' => '10']);

        $this->assertSame('sale_event_enabled', $this->provider([])->getCode());
        $this->assertFalse($this->provider(['current_product' => $product], false)->isApplicable());
        $this->assertTrue($this->provider(['current_product' => $product])->isApplicable());
        $this->assertFalse($this->provider([])->isApplicable());
        $this->assertSame([], $this->provider([])->getJsonLd());
    }

    public function testFeatureFlagLookupSurvivesStoreFailure(): void
    {
        $product = $this->saleProduct(['special_price' => '10']);

        $this->assertTrue(
            $this->provider(['current_product' => $product], true, null, $this->failingStoreManager())->isApplicable()
        );
    }

    public function testSpecialPriceWindowRules(): void
    {
        $cases = [
            'zero price' => [['special_price' => '0'], false],
            'empty price' => [['special_price' => ''], false],
            'starts later' => [['special_price' => '10', 'special_from_date' => '2026-10-15'], false],
            'ended' => [['special_price' => '10', 'special_to_date' => '2026-09-29'], false],
            'ends today' => [['special_price' => '10', 'special_to_date' => '2026-09-30'], true],
            'unparseable dates ignored' => [
                ['special_price' => '10', 'special_from_date' => 'whenever', 'special_to_date' => 'later'],
                true,
            ],
        ];

        foreach ($cases as $label => [$data, $expected]) {
            $product = $this->saleProduct($data);
            $this->assertSame($expected, $this->provider(['current_product' => $product])->isApplicable(), $label);
        }
    }

    public function testProductOfferCarriesValidityWindow(): void
    {
        $product = $this->saleProduct([
            'special_price' => '19.5',
            'special_from_date' => '2026-09-01 00:00:00',
            'special_to_date' => '2026-10-31',
        ]);

        $node = $this->provider(['current_product' => $product], true, null, $this->failingStoreManager())
            ->getJsonLd();

        $this->assertSame([
            '@type' => 'Product',
            '@id' => 'https://example.com/p.html#product',
            'offers' => [
                'validFrom' => '2026-09-01',
                'priceSpecification' => [
                    '@type' => 'UnitPriceSpecification',
                    'price' => '19.50',
                    'priceCurrency' => 'USD',
                    'validFrom' => '2026-09-01',
                    'validThrough' => '2026-10-31',
                ],
            ],
        ], $node);
    }

    public function testProductOfferSkippedForMissingFinalPriceOrZeroConversion(): void
    {
        $noFinal = $this->saleProduct(['special_price' => '10'], ['getFinalPrice' => null]);
        $zeroRate = $this->createStub(PriceCurrencyInterface::class);
        $zeroRate->method('convert')->willReturn(0.0);
        $inactive = $this->saleProduct(['special_price' => '10', 'special_to_date' => '2020-01-01']);

        $this->assertSame([], $this->provider(['current_product' => $noFinal])->getJsonLd());
        $this->assertSame(
            [],
            $this->provider(['current_product' => $this->saleProduct(['special_price' => '10'])], true, $zeroRate)
                ->getJsonLd()
        );
        $this->assertSame([], $this->provider(['current_product' => $inactive])->getJsonLd());
    }

    public function testCategoryWithExplicitEventData(): void
    {
        $category = $this->category([
            'sale_event_name' => ' Summer Sale ',
            'sale_from_date' => '2026-09-01',
            'sale_to_date' => '2026-10-01',
            'sale_event_description' => ' Big discounts ',
        ]);

        $provider = $this->provider(['current_category' => $category]);

        $this->assertTrue($provider->isApplicable());
        $this->assertSame([
            '@type' => 'SaleEvent',
            'name' => 'Summer Sale',
            'startDate' => '2026-09-01',
            'endDate' => '2026-10-01',
            'url' => 'https://example.com/summer.html',
            'description' => 'Big discounts',
        ], $provider->getJsonLd());
    }

    public function testCategoryRangeIsDerivedFromProductsOnSale(): void
    {
        $products = [
            $this->saleProduct(['special_price' => '5', 'special_from_date' => '2026-09-10', 'special_to_date' => '2026-10-05']),
            $this->saleProduct(['special_price' => '6', 'special_from_date' => '2026-09-05', 'special_to_date' => '2026-10-20']),
            $this->saleProduct(['special_price' => '7', 'special_to_date' => '2026-01-01']),
        ];
        $category = $this->category(
            ['sale_event_name' => 'Autumn', 'sale_from_date' => '2027-01-01', 'sale_to_date' => '2027-02-01'],
            $products,
            true
        );

        $provider = $this->provider(['current_category' => $category]);

        $this->assertTrue($provider->isApplicable());
        $this->assertSame([
            '@type' => 'SaleEvent',
            'name' => 'Autumn',
            'startDate' => '2027-01-01',
            'endDate' => '2027-02-01',
        ], $provider->getJsonLd());
    }

    public function testDerivedRangeWithoutDatesUsesTodayAndThirtyDays(): void
    {
        $category = $this->category([], [$this->saleProduct(['special_price' => '5'])]);

        $node = $this->provider(['current_category' => $category])->getJsonLd();

        $this->assertSame('Summer Sale', $node['name']);
        $this->assertSame('2026-09-30', $node['startDate']);
        $this->assertSame('2026-10-30', $node['endDate']);
    }

    public function testDerivedRangeFromProductDates(): void
    {
        $products = [
            $this->saleProduct(['special_price' => '5', 'special_from_date' => '2026-09-10', 'special_to_date' => '2026-10-05']),
            $this->saleProduct(['special_price' => '6', 'special_from_date' => '2026-09-05', 'special_to_date' => '2026-10-20']),
        ];

        $node = $this->provider(['current_category' => $this->category(['sale_event_name' => 'Deals'], $products)])
            ->getJsonLd();

        $this->assertSame('Deals', $node['name']);
        $this->assertSame('2026-09-05', $node['startDate']);
        $this->assertSame('2026-10-20', $node['endDate']);
    }

    public function testCategoryWithoutActiveSalesGivesNothing(): void
    {
        $inactive = $this->category([], [$this->saleProduct(['special_price' => '0'])]);
        $broken = $this->category([], new \RuntimeException('collection'));

        $this->assertFalse($this->provider(['current_category' => $inactive])->isApplicable());
        $this->assertSame([], $this->provider(['current_category' => $inactive])->getJsonLd());
        $this->assertSame([], $this->provider(['current_category' => $broken])->getJsonLd());
    }
}
