<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Shipping;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\Collection;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Shipping\CarrierRateResolver;
use PHPUnit\Framework\TestCase;

class CarrierRateResolverTest extends TestCase
{
    private function resolver(array $flags, array $values, array $rows): CarrierRateResolver
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => (bool) ($flags[$path] ?? false)
        );
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $values[$path] ?? null
        );

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator(
            array_map(static fn (array $row): DataObject => new DataObject($row), $rows)
        ));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new CarrierRateResolver($scopeConfig, $storeManager, $factory);
    }

    private function product(float $price): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getFinalPrice')->willReturn($price);

        return $product;
    }

    private function tableRows(): array
    {
        return [
            ['dest_country_id' => 'US', 'dest_region_id' => 0, 'dest_zip' => '*', 'condition_value' => 0, 'price' => 15],
            ['dest_country_id' => 'US', 'dest_region_id' => 0, 'dest_zip' => '*', 'condition_value' => 50, 'price' => 10],
            ['dest_country_id' => 'US', 'dest_region_id' => 0, 'dest_zip' => '*', 'condition_value' => 100, 'price' => 5],
            ['dest_country_id' => 'US', 'dest_region_id' => 12, 'dest_zip' => '*', 'condition_value' => 0, 'price' => 1],
        ];
    }

    public function testTableRateTierMatchesTheProductPrice(): void
    {
        $resolver = $this->resolver(
            [
                CarrierRateResolver::XML_CARRIER_RATES_ENABLED => true,
                'carriers/tablerate/active' => true,
            ],
            [
                'carriers/tablerate/condition_name' => 'package_value_with_discount',
                'carriers/tablerate/title' => 'Best Way',
            ],
            $this->tableRows()
        );

        $this->assertSame(
            [['label' => 'Best Way', 'country' => 'US', 'cost' => 15.0]],
            $resolver->resolve($this->product(25.0), 1)
        );
        $this->assertSame(10.0, $resolver->resolve($this->product(52.0), 1)[0]['cost']);
        $this->assertSame(5.0, $resolver->resolve($this->product(120.0), 1)[0]['cost']);
    }

    public function testFlatRateIsUsedWhenTableRatesAreInactive(): void
    {
        $resolver = $this->resolver(
            [
                CarrierRateResolver::XML_CARRIER_RATES_ENABLED => true,
                'carriers/flatrate/active' => true,
            ],
            [
                'carriers/flatrate/price' => '5.00',
                'carriers/flatrate/title' => 'Flat Rate',
                'carriers/flatrate/name' => 'Fixed',
            ],
            $this->tableRows()
        );

        $this->assertSame(
            [['label' => 'Flat Rate - Fixed', 'country' => '', 'cost' => 5.0]],
            $resolver->resolve($this->product(25.0), 1)
        );
    }

    public function testNothingWhenCarrierRatesAreDisabled(): void
    {
        $resolver = $this->resolver(
            ['carriers/flatrate/active' => true, 'carriers/tablerate/active' => true],
            ['carriers/flatrate/price' => '5.00', 'carriers/tablerate/condition_name' => 'package_value'],
            $this->tableRows()
        );

        $this->assertSame([], $resolver->resolve($this->product(25.0), 1));
    }
}
