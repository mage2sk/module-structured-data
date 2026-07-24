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
use Panth\StructuredData\Model\StructuredData\Provider\ProductProvider;
use PHPUnit\Framework\TestCase;

class ProductProviderTest extends TestCase
{
    private ProductProvider $provider;

    private $registryMock;

    private $productMock;

    protected function setUp(): void
    {
        $this->registryMock = $this->createMock(Registry::class);
        $requestMock = $this->createMock(RequestInterface::class);

        $storeMock = $this->createMock(Store::class);
        $storeMock->method('getCurrentCurrencyCode')->willReturn('USD');
        $storeMock->method('getId')->willReturn(1);
        $storeMock->method('getBaseUrl')->willReturn('https://example.com/');
        $storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $configMock = $this->createMock(Config::class);
        $configMock->method('getMpnAttribute')->willReturn('');
        $configMock->method('getGtinAttribute')->willReturn('');
        $configMock->method('getBrandAttribute')->willReturn('');
        $configMock->method('getProductConditionSchemaUrl')->willReturn('https://schema.org/NewCondition');
        $configMock->method('getReturnPolicyDays')->willReturn(30);
        $configMock->method('getDeliveryMethods')->willReturn('flatrate');
        $configMock->method('getPriceValidUntilDefault')->willReturn('');

        $imageHelperMock = $this->createMock(ImageHelper::class);
        $imageHelperMock->method('init')->willThrowException(new \RuntimeException('no image'));

        $reviewFactoryMock = $this->createMock(ReviewFactory::class);
        $reviewFactoryMock->method('create')->willThrowException(new \RuntimeException('no reviews'));

        $this->productMock = $this->createMock(Product::class);
        $this->registryMock->method('registry')->willReturnMap([
            ['current_product', $this->productMock],
        ]);

        $this->provider = new ProductProvider(
            $this->registryMock,
            $requestMock,
            $storeManagerMock,
            $configMock,
            $imageHelperMock,
            $this->createMock(PriceCurrencyInterface::class),
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
}
