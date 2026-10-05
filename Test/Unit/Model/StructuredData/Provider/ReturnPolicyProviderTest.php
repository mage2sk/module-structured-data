<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\ReturnPolicyProvider;
use PHPUnit\Framework\TestCase;

class ReturnPolicyProviderTest extends TestCase
{
    public function testReturnMethodComesFromConfigAndPolicyIsLinkedToTheOffer(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getProductUrl')->willReturn('https://example.com/bag.html');

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnMap([
            ['current_product', $product],
        ]);

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['panth_structured_data/structured_data/return_policy_days', 'store', 1, '14'],
            ['panth_structured_data/structured_data/return_policy_type', 'store', 1, ''],
            ['general/country/default', 'store', 1, 'GB'],
        ]);

        $config = $this->createStub(Config::class);
        $config->method('getReturnMethodSchemaUrl')->willReturn('https://schema.org/ReturnInStore');
        $config->method('getReturnFeesSchemaUrl')->willReturn('https://schema.org/FreeReturn');

        $provider = new ReturnPolicyProvider(
            $registry,
            $this->createStub(RequestInterface::class),
            $storeManager,
            $config,
            $scopeConfig
        );

        $node = $provider->getJsonLd();
        $policy = $node['offers']['hasMerchantReturnPolicy'];

        $this->assertSame('https://example.com/bag.html#product', $node['@id']);
        $this->assertSame('MerchantReturnPolicy', $policy['@type']);
        $this->assertSame('https://schema.org/ReturnInStore', $policy['returnMethod']);
        $this->assertSame(14, $policy['merchantReturnDays']);
        $this->assertSame('GB', $policy['applicableCountry']);
    }
}
