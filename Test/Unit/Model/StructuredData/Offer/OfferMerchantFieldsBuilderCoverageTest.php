<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Offer;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Offer\OfferMerchantFieldsBuilder;
use Panth\StructuredData\Model\StructuredData\Shipping\CarrierRateResolver;
use PHPUnit\Framework\TestCase;

class OfferMerchantFieldsBuilderCoverageTest extends TestCase
{
    private function realConfig(array $values): Config
    {
        $flags = [
            Config::XML_SD_MERCHANT_FIELDS_ENABLED => true,
            Config::XML_SD_MERCHANT_RETURN_ENABLED => true,
            Config::XML_SD_MERCHANT_SHIPPING_ENABLED => true,
        ];
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(static fn(string $p) => $flags[$p] ?? false);
        $scopeConfig->method('getValue')->willReturnCallback(static fn(string $p) => $values[$p] ?? null);

        return new Config($scopeConfig);
    }

    private function simple(): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('simple');

        return $product;
    }

    public function testIncompleteCarrierRowsUseShippingCountryAndClampCost(): void
    {
        $resolver = $this->createStub(CarrierRateResolver::class);
        $resolver->method('resolve')->willReturn([['cost' => -5], []]);

        $offer = (new OfferMerchantFieldsBuilder($this->realConfig([Config::XML_SD_SHIPPING_COUNTRY => 'nl']), $resolver))
            ->apply(['@type' => 'Offer'], $this->simple(), 'EUR', 2);

        $this->assertCount(2, $offer['shippingDetails']);
        foreach ($offer['shippingDetails'] as $detail) {
            $this->assertSame('0.00', $detail['shippingRate']['value']);
            $this->assertSame('EUR', $detail['shippingRate']['currency']);
            $this->assertSame('NL', $detail['shippingDestination']['addressCountry']);
            $this->assertArrayNotHasKey('shippingLabel', $detail);
        }
    }

    public function testRealConfigDrivesReturnPolicyAndShippingWithoutResolver(): void
    {
        $offer = (new OfferMerchantFieldsBuilder($this->realConfig([
            Config::XML_STORE_COUNTRY => 'GB',
            Config::XML_SD_RETURN_POLICY_DAYS => '21',
            Config::XML_SD_RETURN_METHOD => 'kiosk',
            Config::XML_SD_RETURN_FEES => 'RestockingFees',
            Config::XML_SD_SHIPPING_DEFAULT_RATE => '3',
            Config::XML_SD_SHIPPING_TRANSIT_MIN => '2',
            Config::XML_SD_SHIPPING_TRANSIT_MAX => '4',
        ])))->apply(['@type' => 'Offer'], $this->simple(), 'GBP');

        $this->assertSame([
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'GB',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 21,
            'returnMethod' => 'https://schema.org/ReturnAtKiosk',
            'returnFees' => 'https://schema.org/RestockingFees',
        ], $offer['hasMerchantReturnPolicy']);
        $this->assertSame('3.00', $offer['shippingDetails']['shippingRate']['value']);
        $this->assertSame('GB', $offer['shippingDetails']['shippingDestination']['addressCountry']);
        $this->assertSame(2, $offer['shippingDetails']['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(4, $offer['shippingDetails']['deliveryTime']['transitTime']['maxValue']);
    }
}
