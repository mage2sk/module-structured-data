<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Shipping;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\Collection;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Shipping\CarrierRateResolver;
use PHPUnit\Framework\TestCase;

class CarrierRateResolverCoverageTest extends TestCase
{
    private const TABLE_FLAGS = [
        CarrierRateResolver::XML_CARRIER_RATES_ENABLED => true,
        'carriers/tablerate/active' => true,
    ];

    private function resolver(
        array $flags,
        array $values,
        array $rows = [],
        bool $withFactory = true,
        ?PriceCurrencyInterface $priceCurrency = null,
        bool $storeFails = false
    ): CarrierRateResolver {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => (bool) ($flags[$path] ?? false)
        );
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[$path] ?? null
        );

        $storeManager = $this->createStub(StoreManagerInterface::class);
        if ($storeFails) {
            $storeManager->method('getStore')->willThrowException(new \RuntimeException('store'));
        } else {
            $store = $this->createStub(Store::class);
            $store->method('getWebsiteId')->willReturn(1);
            $storeManager->method('getStore')->willReturn($store);
        }

        $factory = null;
        if ($withFactory) {
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnSelf();
            $collection->method('setPageSize')->willReturnSelf();
            $collection->method('getIterator')->willReturn(new \ArrayIterator(
                array_map(static fn(array $row): DataObject => new DataObject($row), $rows)
            ));
            $factory = $this->createStub(CollectionFactory::class);
            $factory->method('create')->willReturn($collection);
        }

        return new CarrierRateResolver($scopeConfig, $storeManager, $factory, $priceCurrency);
    }

    private function product(mixed $finalPrice = 10.0, float $price = 0.0, float $weight = 0.0): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getPrice')->willReturn($price);
        $product->method('getWeight')->willReturn($weight);

        return $product;
    }

    private static function row(string $country, float $threshold, float $price, string $zip = '*'): array
    {
        return [
            'dest_country_id' => $country,
            'dest_region_id' => 0,
            'dest_zip' => $zip,
            'condition_value' => $threshold,
            'price' => $price,
        ];
    }

    public function testNoTableFactoryAndInactiveFlatRateGivesNothing(): void
    {
        $resolver = $this->resolver(self::TABLE_FLAGS, ['carriers/tablerate/condition_name' => 'package_value'], [], false);

        $this->assertSame([], $resolver->resolve($this->product(), 1));
    }

    public function testEmptyConditionFallsBackToFlatRateWithTitleOnly(): void
    {
        $resolver = $this->resolver(
            self::TABLE_FLAGS + ['carriers/flatrate/active' => true],
            ['carriers/flatrate/price' => '-3', 'carriers/flatrate/title' => ' Flat ']
        );

        $this->assertSame(
            [['label' => 'Flat', 'country' => '', 'cost' => 0.0]],
            $resolver->resolve($this->product(), 1)
        );
    }

    public function testWeightConditionUsesProductWeightAndPicksHighestMatchingTierPerCountry(): void
    {
        $resolver = $this->resolver(
            self::TABLE_FLAGS,
            ['carriers/tablerate/condition_name' => 'package_weight'],
            [
                self::row('gb', 5, 8),
                self::row('GB', 1, 12),
                self::row('GB', 3, 9),
                self::row('GB', 20, 2),
                self::row('DE', 0, -4),
                self::row('*', 0, 30),
                self::row('FR', 0, 7, '75001'),
            ]
        );

        $this->assertSame(
            [
                ['label' => '', 'country' => 'GB', 'cost' => 8.0],
                ['label' => '', 'country' => 'DE', 'cost' => 0.0],
            ],
            $resolver->resolve($this->product(10.0, 0.0, 6.0), 1)
        );
    }

    public function testWildcardCountryKeptWhenItIsTheOnlyRow(): void
    {
        $resolver = $this->resolver(
            self::TABLE_FLAGS,
            ['carriers/tablerate/condition_name' => 'package_qty', 'carriers/tablerate/title' => 'Table'],
            [self::row('0', 1, 6), self::row('US', 2, 1)]
        );

        $this->assertSame(
            [['label' => 'Table', 'country' => '', 'cost' => 6.0]],
            $resolver->resolve($this->product(), 1)
        );
    }

    public function testValueConditionFallsBackToBasePrice(): void
    {
        $resolver = $this->resolver(
            self::TABLE_FLAGS,
            ['carriers/tablerate/condition_name' => 'package_value'],
            [self::row('US', 0, 15), self::row('US', 40, 4)]
        );

        $this->assertSame(4.0, $resolver->resolve($this->product(null, 45.0), 1)[0]['cost']);
    }

    public function testNoMatchingTierFallsBackToFlatRate(): void
    {
        $resolver = $this->resolver(
            self::TABLE_FLAGS + ['carriers/flatrate/active' => true],
            [
                'carriers/tablerate/condition_name' => 'package_value',
                'carriers/flatrate/price' => '2.5',
                'carriers/flatrate/name' => 'Fixed',
            ],
            [self::row('US', 100, 1)]
        );

        $this->assertSame(
            [['label' => 'Fixed', 'country' => '', 'cost' => 2.5]],
            $resolver->resolve($this->product(10.0), 1)
        );
    }

    public function testCurrencyConversion(): void
    {
        $flags = [CarrierRateResolver::XML_CARRIER_RATES_ENABLED => true, 'carriers/flatrate/active' => true];
        $values = ['carriers/flatrate/price' => '10'];

        $double = $this->createStub(PriceCurrencyInterface::class);
        $double->method('convert')->willReturnCallback(static fn($amount) => $amount * 2);
        $throwing = $this->createStub(PriceCurrencyInterface::class);
        $throwing->method('convert')->willThrowException(new \RuntimeException('rate'));
        $garbage = $this->createStub(PriceCurrencyInterface::class);
        $garbage->method('convert')->willReturn('n/a');

        $this->assertSame(20.0, $this->resolver($flags, $values, [], true, $double)->resolve($this->product())[0]['cost']);
        $this->assertSame(10.0, $this->resolver($flags, $values, [], true, $throwing)->resolve($this->product())[0]['cost']);
        $this->assertSame(10.0, $this->resolver($flags, $values, [], true, $garbage)->resolve($this->product())[0]['cost']);
    }

    public function testFailuresAreSwallowed(): void
    {
        $resolver = $this->resolver(
            self::TABLE_FLAGS,
            ['carriers/tablerate/condition_name' => 'package_value'],
            [],
            true,
            null,
            true
        );

        $this->assertSame([], $resolver->resolve($this->product(), 1));
    }
}
