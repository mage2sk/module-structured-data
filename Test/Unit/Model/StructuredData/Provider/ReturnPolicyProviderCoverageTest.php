<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\ReturnPolicyProvider;

class ReturnPolicyProviderCoverageTest extends AbstractProviderTestCase
{
    private const DAYS = 'panth_structured_data/structured_data/return_policy_days';
    private const TYPE = 'panth_structured_data/structured_data/return_policy_type';

    private function provider(
        array $registry,
        array $values,
        array $flags = [],
        ?StoreManagerInterface $storeManager = null
    ): ReturnPolicyProvider {
        return new ReturnPolicyProvider(
            $this->registry($registry),
            $this->request(),
            $storeManager ?? $this->storeManager(),
            $this->config($values, $flags),
            $this->scopeConfig($values, $flags)
        );
    }

    private function withProduct(array $data = []): array
    {
        return ['current_product' => $this->product(['getProductUrl' => 'https://example.com/p.html'], $data)];
    }

    public function testApplicabilityRules(): void
    {
        $days = [self::DAYS => '30'];

        $this->assertSame('return_policy', $this->provider([], $days)->getCode());
        $this->assertFalse($this->provider([], $days)->isApplicable());
        $this->assertFalse($this->provider($this->withProduct(), [self::DAYS => '0'])->isApplicable());
        $this->assertFalse(
            $this->provider($this->withProduct(), $days, [Config::XML_SD_MERCHANT_FIELDS_ENABLED => true])
                ->isApplicable()
        );
        $this->assertFalse(
            $this->provider(
                $this->withProduct([Config::SOFTWARE_ATTRIBUTE => '1']),
                $days,
                [Config::XML_SD_SOFTWARE_APPLICATION => true]
            )->isApplicable()
        );
        $this->assertTrue($this->provider($this->withProduct(), $days)->isApplicable());
        $this->assertTrue($this->provider($this->withProduct(), $days, [], $this->failingStoreManager())->isApplicable());
    }

    public function testNodeWithReturnTypeAndCountryFallback(): void
    {
        $node = $this->provider(
            $this->withProduct(),
            [self::DAYS => '7', self::TYPE => 'exchange', Config::XML_SD_RETURN_FEES => 'returnshippingfees'],
            [],
            $this->failingStoreManager()
        )->getJsonLd();

        $policy = $node['offers']['hasMerchantReturnPolicy'];
        $this->assertSame('US', $policy['applicableCountry']);
        $this->assertSame(7, $policy['merchantReturnDays']);
        $this->assertSame('https://schema.org/MerchantReturnFiniteReturnWindow', $policy['returnPolicyCategory']);
        $this->assertSame('https://schema.org/ReturnByMail', $policy['returnMethod']);
        $this->assertSame('https://schema.org/ReturnShippingFees', $policy['returnFees']);
        $this->assertSame(
            ['@type' => 'PropertyValue', 'name' => 'Return Type', 'value' => 'exchange'],
            $policy['additionalProperty']
        );
    }

    public function testNoNodeWithoutProductOrDays(): void
    {
        $this->assertSame([], $this->provider([], [self::DAYS => '30'])->getJsonLd());
        $this->assertSame([], $this->provider($this->withProduct(), [])->getJsonLd());
    }
}
