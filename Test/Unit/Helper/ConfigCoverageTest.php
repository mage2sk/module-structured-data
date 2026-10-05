<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Helper;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\StructuredData\Helper\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigCoverageTest extends TestCase
{
    private function config(array $values = [], array $flags = []): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path) => (bool) ($flags[$path] ?? false)
        );

        return new Config($scopeConfig);
    }

    public function testFlagsAreReadAtStoreScope(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_GENERAL_ENABLED, ScopeInterface::SCOPE_STORE, 4)
            ->willReturn(true);

        $this->assertTrue((new Config($scopeConfig))->isEnabled(4));
    }

    #[DataProvider('flagMethodProvider')]
    public function testSimpleFlagGetters(string $method, string $path): void
    {
        $this->assertFalse($this->config()->{$method}(1));
        $this->assertTrue($this->config([], [$path => true])->{$method}(1));
    }

    public static function flagMethodProvider(): array
    {
        return [
            'enabled' => ['isEnabled', Config::XML_GENERAL_ENABLED],
            'debug' => ['isDebug', Config::XML_GENERAL_DEBUG],
            'product group' => ['isProductGroupEnabled', Config::XML_SD_PRODUCT_GROUP],
            'product list' => ['isProductListSchemaEnabled', Config::XML_SD_PRODUCT_LIST_SCHEMA],
            'merchant fields' => ['isMerchantFieldsEnabled', Config::XML_SD_MERCHANT_FIELDS_ENABLED],
            'merchant return' => ['isMerchantReturnEnabled', Config::XML_SD_MERCHANT_RETURN_ENABLED],
            'merchant shipping' => ['isMerchantShippingEnabled', Config::XML_SD_MERCHANT_SHIPPING_ENABLED],
            'brand fallback' => ['isBrandStoreNameFallbackEnabled', Config::XML_SD_BRAND_STORE_FALLBACK],
            'software' => ['isSoftwareApplicationEnabled', Config::XML_SD_SOFTWARE_APPLICATION],
        ];
    }

    #[DataProvider('mappedFlagProvider')]
    public function testStructuredDataFlagCodesMapToConfigKeys(string $code, string $key): void
    {
        $path = 'panth_structured_data/structured_data/' . $key;

        $this->assertFalse($this->config()->isStructuredDataEnabled($code));
        $this->assertTrue($this->config([], [$path => true])->isStructuredDataEnabled($code));
    }

    public static function mappedFlagProvider(): array
    {
        return [
            'configurable offer' => ['configurable_offer', 'configurable_multi_offer'],
            'product list' => ['productList', 'enable_product_list_schema'],
            'product group' => ['product_group', 'product_group_enabled'],
            'pros cons' => ['pros_cons', 'pros_cons_enabled'],
            'bundle' => ['bundle_offer', 'product'],
            'grouped' => ['grouped_offer', 'product'],
            'unmapped' => ['organization', 'organization'],
        ];
    }

    #[DataProvider('valueCodeProvider')]
    public function testValueBasedCodesNeedNonEmptyNonZeroValue(string $code, string $key, mixed $value, bool $expected): void
    {
        $config = $this->config(['panth_structured_data/structured_data/' . $key => $value]);

        $this->assertSame($expected, $config->isStructuredDataEnabled($code));
    }

    public static function valueCodeProvider(): array
    {
        return [
            'return days set' => ['return_policy', 'return_policy_days', '30', true],
            'return days zero' => ['return_policy', 'return_policy_days', '0', false],
            'return days unset' => ['return_policy', 'return_policy_days', null, false],
            'payment set' => ['paymentMethod', 'accepted_payment_methods', 'Visa', true],
            'payment empty' => ['paymentMethod', 'accepted_payment_methods', '', false],
            'custom props set' => ['custom_properties', 'custom_properties', '{"a":1}', true],
        ];
    }

    public function testAttributeAndTextGettersWithDefaults(): void
    {
        $empty = $this->config();

        $this->assertSame('manufacturer', $empty->getBrandAttribute());
        $this->assertSame('', $empty->getGtinAttribute());
        $this->assertSame('', $empty->getMpnAttribute());
        $this->assertSame(30, $empty->getReturnPolicyDays());
        $this->assertSame('', $empty->getAcceptedPaymentMethods());
        $this->assertSame('', $empty->getDeliveryMethods());
        $this->assertSame('', $empty->getPriceValidUntilDefault());
        $this->assertSame('', $empty->getDefaultBrand());
        $this->assertSame('', $empty->getStoreName());
        $this->assertSame('US', $empty->getStoreCountry());
        $this->assertSame('0.00', $empty->getShippingDefaultRate());
        $this->assertSame(0, $empty->getShippingHandlingMin());
        $this->assertSame(1, $empty->getShippingHandlingMax());
        $this->assertSame(1, $empty->getShippingTransitMin());
        $this->assertSame(5, $empty->getShippingTransitMax());
        $this->assertNull($empty->getValue('any/path'));
    }

    public function testAttributeAndTextGettersWithValues(): void
    {
        $config = $this->config([
            Config::XML_SD_BRAND_ATTRIBUTE => 'brand',
            Config::XML_SD_GTIN_ATTRIBUTE => 'ean',
            Config::XML_SD_MPN_ATTRIBUTE => 'mpn',
            Config::XML_SD_RETURN_POLICY_DAYS => '14',
            Config::XML_SD_ACCEPTED_PAYMENT => 'Visa,PayPal',
            Config::XML_SD_DELIVERY_METHODS => 'Std|1|2|0',
            Config::XML_SD_PRICE_VALID_UNTIL_DEFAULT => ' 2030-01-01 ',
            Config::XML_SD_DEFAULT_BRAND => ' Acme ',
            Config::XML_STORE_NAME => ' Shop ',
            Config::XML_STORE_COUNTRY => ' gb ',
            Config::XML_SD_SHIPPING_DEFAULT_RATE => '-2',
            Config::XML_SD_SHIPPING_HANDLING_MIN => '3',
            Config::XML_SD_SHIPPING_HANDLING_MAX => '1',
            Config::XML_SD_SHIPPING_TRANSIT_MIN => '-1',
            Config::XML_SD_SHIPPING_TRANSIT_MAX => '7',
            'custom/path' => 'raw',
        ]);

        $this->assertSame('brand', $config->getBrandAttribute());
        $this->assertSame('ean', $config->getGtinAttribute());
        $this->assertSame('mpn', $config->getMpnAttribute());
        $this->assertSame(14, $config->getReturnPolicyDays());
        $this->assertSame('Visa,PayPal', $config->getAcceptedPaymentMethods());
        $this->assertSame('Std|1|2|0', $config->getDeliveryMethods());
        $this->assertSame('2030-01-01', $config->getPriceValidUntilDefault());
        $this->assertSame('Acme', $config->getDefaultBrand());
        $this->assertSame('Shop', $config->getStoreName());
        $this->assertSame('GB', $config->getStoreCountry());
        $this->assertSame('GB', $config->getReturnApplicableCountry());
        $this->assertSame('0.00', $config->getShippingDefaultRate());
        $this->assertSame(3, $config->getShippingHandlingMin());
        $this->assertSame(3, $config->getShippingHandlingMax());
        $this->assertSame(0, $config->getShippingTransitMin());
        $this->assertSame(7, $config->getShippingTransitMax());
        $this->assertSame('raw', $config->getValue('custom/path'));
    }

    #[DataProvider('conditionProvider')]
    public function testProductConditionMapping(?string $stored, string $expected): void
    {
        $config = $this->config([Config::XML_SD_PRODUCT_CONDITION => $stored]);

        $this->assertSame($expected, $config->getProductConditionSchemaUrl());
    }

    public static function conditionProvider(): array
    {
        return [
            'unset' => [null, 'https://schema.org/NewCondition'],
            'used' => ['used', 'https://schema.org/UsedCondition'],
            'refurbished' => ['refurbished', 'https://schema.org/RefurbishedCondition'],
            'damaged' => ['damaged', 'https://schema.org/DamagedCondition'],
            'unknown' => ['broken', 'https://schema.org/NewCondition'],
        ];
    }

    public function testSocialProfilesKeepOnlySafeHttpUrls(): void
    {
        $config = $this->config([
            Config::XML_SOCIAL_PROFILE_FACEBOOK => ' https://facebook.com/acme ',
            Config::XML_SOCIAL_PROFILE_TWITTER => 'javascript:alert(1)',
            Config::XML_SOCIAL_PROFILE_INSTAGRAM => 'ftp://files.example.com/x',
            Config::XML_SOCIAL_PROFILE_LINKEDIN => 'not a url',
            Config::XML_SOCIAL_PROFILE_YOUTUBE => '',
            Config::XML_SOCIAL_PROFILE_PINTEREST => 'http://pinterest.com/acme',
            Config::XML_SOCIAL_PROFILE_TIKTOK => 'HTTPS://tiktok.com/@acme',
        ]);

        $this->assertSame(
            ['https://facebook.com/acme', 'http://pinterest.com/acme', 'HTTPS://tiktok.com/@acme'],
            $config->getSocialProfileUrls(1)
        );
    }

    public function testSoftwareProductSwallowsAttributeErrors(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willThrowException(new \RuntimeException('eav'));

        $config = $this->config([], [Config::XML_SD_SOFTWARE_APPLICATION => true]);

        $this->assertFalse($config->isSoftwareProduct($product, 1));
    }
}
