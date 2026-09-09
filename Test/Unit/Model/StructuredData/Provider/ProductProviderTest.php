<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Offer\OfferMerchantFieldsBuilder;
use Panth\StructuredData\Model\StructuredData\Provider\ProductProvider;
use PHPUnit\Framework\TestCase;

class ProductProviderTest extends TestCase
{
    private ProductProvider $provider;

    private $registryMock;

    private $productMock;

    protected function setUp(): void
    {
        $this->registryMock = $this->createStub(Registry::class);
        $requestMock = $this->createStub(RequestInterface::class);

        $storeMock = $this->createStub(Store::class);
        $storeMock->method('getCurrentCurrencyCode')->willReturn('USD');
        $storeMock->method('getId')->willReturn(1);
        $storeMock->method('getBaseUrl')->willReturn('https://example.com/');
        $storeManagerMock = $this->createStub(StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $configMock = $this->createStub(Config::class);
        $configMock->method('getMpnAttribute')->willReturn('');
        $configMock->method('getGtinAttribute')->willReturn('');
        $configMock->method('getBrandAttribute')->willReturn('');
        $configMock->method('getProductConditionSchemaUrl')->willReturn('https://schema.org/NewCondition');
        $configMock->method('getReturnPolicyDays')->willReturn(30);
        $configMock->method('getDeliveryMethods')->willReturn('flatrate');
        $configMock->method('getPriceValidUntilDefault')->willReturn('');

        $imageHelperMock = $this->createStub(ImageHelper::class);
        $imageHelperMock->method('init')->willThrowException(new \RuntimeException('no image'));

        $reviewFactoryMock = $this->createStub(ReviewFactory::class);
        $reviewFactoryMock->method('create')->willThrowException(new \RuntimeException('no reviews'));

        $this->productMock = $this->createStub(Product::class);
        $this->registryMock->method('registry')->willReturnMap([
            ['current_product', $this->productMock],
        ]);

        $this->provider = new ProductProvider(
            $this->registryMock,
            $requestMock,
            $storeManagerMock,
            $configMock,
            $imageHelperMock,
            $this->createStub(PriceCurrencyInterface::class),
            $reviewFactoryMock,
            null,
            null
        );
    }

    public function testProductNodeSurvivesMissingGenderAttribute(): void
    {
        $this->productMock->method('getAttributeText')->willThrowException(
            new \Error('Call to a member function getSource() on false')
        );
        $this->productMock->method('getProductUrl')->willReturn('https://example.com/test-product.html');
        $this->productMock->method('getName')->willReturn('Test Product');
        $this->productMock->method('getSku')->willReturn('TEST-SKU');
        $this->productMock->method('getTypeId')->willReturn('simple');
        $this->productMock->method('getFinalPrice')->willReturn(19.99);
        $this->productMock->method('getData')->willReturn(null);
        $this->productMock->method('hasData')->willReturn(false);
        $this->productMock->method('isAvailable')->willReturn(true);
        $this->productMock->method('getMediaGalleryImages')->willReturn(null);

        $node = $this->provider->getJsonLd();

        $this->assertSame('Product', $node['@type']);
        $this->assertSame('Test Product', $node['name']);
        $this->assertSame('TEST-SKU', $node['sku']);
        $this->assertArrayHasKey('offers', $node);
        $this->assertSame('19.99', $node['offers']['price']);
        $this->assertArrayNotHasKey('audience', $node);
        $this->assertArrayNotHasKey('brand', $node);
    }

    public function testAudienceAndBrandPopulatedWhenAttributesExist(): void
    {
        $this->productMock->method('getAttributeText')->willReturnMap([
            ['manufacturer', 'Acme Brand'],
            ['gender', 'Unisex'],
        ]);
        $this->productMock->method('getProductUrl')->willReturn('https://example.com/test-product.html');
        $this->productMock->method('getName')->willReturn('Test Product');
        $this->productMock->method('getSku')->willReturn('TEST-SKU');
        $this->productMock->method('getTypeId')->willReturn('simple');
        $this->productMock->method('getFinalPrice')->willReturn(19.99);
        $this->productMock->method('getData')->willReturn(null);
        $this->productMock->method('hasData')->willReturn(false);
        $this->productMock->method('isAvailable')->willReturn(true);
        $this->productMock->method('getMediaGalleryImages')->willReturn(null);

        $node = $this->provider->getJsonLd();

        $this->assertSame(['@type' => 'Brand', 'name' => 'Acme Brand'], $node['brand']);
        $this->assertSame(['@type' => 'PeopleAudience', 'audienceType' => 'Unisex'], $node['audience']);
    }

    public function testFreeVirtualProductStillEmitsAValidOffer(): void
    {
        $provider = $this->buildProvider(
            ['isMerchantFieldsEnabled' => true],
            ['getTypeId' => 'virtual', 'getFinalPrice' => 0.0]
        );

        $node = $provider->getJsonLd();

        $this->assertArrayHasKey('offers', $node);
        $this->assertSame('0.00', $node['offers']['price']);
        $this->assertSame('USD', $node['offers']['priceCurrency']);
        $this->assertArrayHasKey('availability', $node['offers']);
        $this->assertSame(
            'https://schema.org/MerchantReturnNotPermitted',
            $node['offers']['hasMerchantReturnPolicy']['returnPolicyCategory']
        );
        $this->assertSame('OfferShippingDetails', $node['offers']['shippingDetails']['@type']);
    }

    public function testNegativeFinalPriceIsClampedToZero(): void
    {
        $provider = $this->buildProvider([], ['getFinalPrice' => -5.0]);

        $node = $provider->getJsonLd();

        $this->assertSame('0.00', $node['offers']['price']);
    }

    public function testBrandFallsBackToStoreName(): void
    {
        $provider = $this->buildProvider([
            'isMerchantFieldsEnabled' => true,
            'isBrandStoreNameFallbackEnabled' => true,
            'getStoreName' => 'Acme Store',
        ]);

        $node = $provider->getJsonLd();

        $this->assertSame(['@type' => 'Brand', 'name' => 'Acme Store'], $node['brand']);
    }

    public function testDefaultBrandWinsOverStoreName(): void
    {
        $provider = $this->buildProvider([
            'isMerchantFieldsEnabled' => true,
            'isBrandStoreNameFallbackEnabled' => true,
            'getStoreName' => 'Acme Store',
            'getDefaultBrand' => 'Preferred Brand',
        ]);

        $node = $provider->getJsonLd();

        $this->assertSame(['@type' => 'Brand', 'name' => 'Preferred Brand'], $node['brand']);
    }

    public function testBrandOmittedWhenFallbackDisabled(): void
    {
        $provider = $this->buildProvider([
            'isMerchantFieldsEnabled' => true,
            'isBrandStoreNameFallbackEnabled' => false,
            'getStoreName' => 'Acme Store',
        ]);

        $node = $provider->getJsonLd();

        $this->assertArrayNotHasKey('brand', $node);
    }

    public function testMerchantFieldsAbsentWhenBuilderIsNotWired(): void
    {
        $node = $this->provider->getJsonLd();

        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $node['offers']);
        $this->assertArrayNotHasKey('shippingDetails', $node['offers']);
    }

    private function buildProvider(array $configOverrides = [], array $productOverrides = []): ProductProvider
    {
        $storeMock = $this->createStub(Store::class);
        $storeMock->method('getCurrentCurrencyCode')->willReturn('USD');
        $storeMock->method('getId')->willReturn(1);
        $storeMock->method('getBaseUrl')->willReturn('https://example.com/');
        $storeManagerMock = $this->createStub(StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $configDefaults = [
            'getMpnAttribute' => '',
            'getGtinAttribute' => '',
            'getBrandAttribute' => '',
            'getDefaultBrand' => '',
            'getProductConditionSchemaUrl' => 'https://schema.org/NewCondition',
            'getReturnPolicyDays' => 30,
            'getDeliveryMethods' => '',
            'getPriceValidUntilDefault' => '',
            'isMerchantFieldsEnabled' => false,
            'isMerchantReturnEnabled' => true,
            'isMerchantShippingEnabled' => true,
            'isBrandStoreNameFallbackEnabled' => false,
            'getStoreName' => '',
            'getReturnApplicableCountry' => 'US',
            'getShippingCountry' => 'US',
            'getReturnMethodSchemaUrl' => 'https://schema.org/ReturnByMail',
            'getReturnFeesSchemaUrl' => 'https://schema.org/FreeReturn',
            'getShippingDefaultRate' => '0.00',
            'getShippingHandlingMin' => 0,
            'getShippingHandlingMax' => 1,
            'getShippingTransitMin' => 1,
            'getShippingTransitMax' => 5,
        ];
        $configMock = $this->createStub(Config::class);
        foreach (array_merge($configDefaults, $configOverrides) as $method => $value) {
            $configMock->method($method)->willReturn($value);
        }

        $productDefaults = [
            'getProductUrl' => 'https://example.com/test-product.html',
            'getName' => 'Test Product',
            'getSku' => 'TEST-SKU',
            'getTypeId' => 'simple',
            'getFinalPrice' => 19.99,
            'getData' => null,
            'hasData' => false,
            'isAvailable' => true,
            'getMediaGalleryImages' => null,
        ];
        $productMock = $this->createStub(Product::class);
        $productMock->method('getAttributeText')->willReturn(null);
        foreach (array_merge($productDefaults, $productOverrides) as $method => $value) {
            $productMock->method($method)->willReturn($value);
        }

        $registryMock = $this->createStub(Registry::class);
        $registryMock->method('registry')->willReturnMap([['current_product', $productMock]]);

        $imageHelperMock = $this->createStub(ImageHelper::class);
        $imageHelperMock->method('init')->willThrowException(new \RuntimeException('no image'));

        $reviewFactoryMock = $this->createStub(ReviewFactory::class);
        $reviewFactoryMock->method('create')->willThrowException(new \RuntimeException('no reviews'));

        return new ProductProvider(
            $registryMock,
            $this->createStub(RequestInterface::class),
            $storeManagerMock,
            $configMock,
            $imageHelperMock,
            $this->createStub(PriceCurrencyInterface::class),
            $reviewFactoryMock,
            null,
            null,
            null,
            new OfferMerchantFieldsBuilder($configMock)
        );
    }
}
