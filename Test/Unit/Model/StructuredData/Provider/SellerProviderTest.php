<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\SellerProvider;

class SellerProviderTest extends AbstractProviderTestCase
{
    private const TYPE = 'panth_structured_data/structured_data/business_type';

    private function provider(
        array $values,
        array $registry = [],
        array $flags = [],
        ?StoreManagerInterface $storeManager = null
    ): SellerProvider {
        return new SellerProvider(
            $this->registry($registry),
            $this->request(),
            $storeManager ?? $this->storeManager($this->store(1, 'https://shop.test', 'Shop Name')),
            $this->config([], $flags),
            $this->scopeConfig($values)
        );
    }

    public function testApplicabilityNeedsProductAndOrganizationFlag(): void
    {
        $product = ['current_product' => $this->product()];
        $flag = ['panth_structured_data/structured_data/organization' => true];

        $this->assertSame('seller', $this->provider([])->getCode());
        $this->assertFalse($this->provider([], [], $flag)->isApplicable());
        $this->assertFalse($this->provider([], $product)->isApplicable());
        $this->assertTrue($this->provider([], $product, $flag)->isApplicable());
    }

    public function testUnknownTypeFallsBackToOrganizationWithoutAddress(): void
    {
        $node = $this->provider([
            self::TYPE => 'Spaceship',
            'general/store_information/city' => 'Town',
            'general/store_information/phone' => '123',
        ])->getJsonLd();

        $this->assertSame([
            '@type' => 'Organization',
            '@id' => 'https://shop.test/#organization',
            'name' => 'Shop Name',
            'url' => 'https://shop.test/',
        ], $node);
    }

    public function testLocalBusinessGetsAddressAndPhone(): void
    {
        $node = $this->provider([
            self::TYPE => 'LocalBusiness',
            'general/store_information/name' => 'Configured',
            'general/store_information/street_line1' => '1 Road',
            'general/store_information/street_line2' => 'Unit 2',
            'general/store_information/city' => 'Town',
            'general/store_information/region_id' => '12',
            'general/store_information/postcode' => 'AB1',
            'general/store_information/country_id' => 'GB',
            'general/store_information/phone' => '123',
        ])->getJsonLd();

        $this->assertSame('LocalBusiness', $node['@type']);
        $this->assertSame('Configured', $node['name']);
        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '1 Road, Unit 2',
            'addressLocality' => 'Town',
            'addressRegion' => '12',
            'postalCode' => 'AB1',
            'addressCountry' => 'GB',
        ], $node['address']);
        $this->assertSame('123', $node['telephone']);
    }

    public function testStoreTypeWithoutAddressDataOmitsAddressAndPhone(): void
    {
        $node = $this->provider([self::TYPE => 'Store', 'general/store_information/region_id' => '5'])->getJsonLd();

        $this->assertSame('Store', $node['@type']);
        $this->assertArrayNotHasKey('address', $node);
        $this->assertArrayNotHasKey('telephone', $node);
    }

    public function testStoreFailureGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([], [], [], $this->failingStoreManager())->getJsonLd());
    }

    public function testNameFallsBackToStoreWhenSecondLookupFails(): void
    {
        $store = $this->store(1, 'https://shop.test/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $calls = 0;
        $storeManager->method('getStore')->willReturnCallback(
            static function () use ($store, &$calls): Store {
                if (++$calls > 1) {
                    throw new \RuntimeException('gone');
                }
                return $store;
            }
        );

        $this->assertSame('Store', $this->provider([], [], [], $storeManager)->getJsonLd()['name']);
    }
}
