<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Shipping;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Shipping\ShippingDetailsBuilder;
use PHPUnit\Framework\TestCase;

class ShippingDetailsBuilderTest extends TestCase
{
    private function builder(array $values): ShippingDetailsBuilder
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[$path] ?? null
        );

        return new ShippingDetailsBuilder(new Config($scopeConfig), $scopeConfig);
    }

    public function testNoDeliveryMethodsGivesNothing(): void
    {
        $this->assertSame([], $this->builder([])->build('USD', 1));
        $this->assertSame(
            [],
            $this->builder([Config::XML_SD_DELIVERY_METHODS => "# only a comment\n | 1 | 2"])->build('USD')
        );
    }

    public function testSixColumnRowCarriesItsOwnHandlingTime(): void
    {
        $details = $this->builder([
            Config::XML_SD_DELIVERY_METHODS => 'Express | 2 | 1 | 3 | 1 | -4',
            'general/country/default' => 'DE',
        ])->build('EUR', 1);

        $this->assertCount(1, $details);
        $detail = $details[0];
        $this->assertSame('OfferShippingDetails', $detail['@type']);
        $this->assertSame('Express', $detail['shippingLabel']);
        $this->assertSame(['@type' => 'MonetaryAmount', 'value' => '0.00', 'currency' => 'EUR'], $detail['shippingRate']);
        $this->assertSame(['@type' => 'DefinedRegion', 'addressCountry' => 'DE'], $detail['shippingDestination']);
        $this->assertSame(2, $detail['deliveryTime']['handlingTime']['minValue']);
        $this->assertSame(2, $detail['deliveryTime']['handlingTime']['maxValue']);
        $this->assertSame(3, $detail['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(3, $detail['deliveryTime']['transitTime']['maxValue']);
        $this->assertSame('DAY', $detail['deliveryTime']['transitTime']['unitCode']);
        $this->assertCount(5, $detail['deliveryTime']['businessDays']['dayOfWeek']);
    }

    public function testShortRowsUseConfiguredHandlingAndDefaults(): void
    {
        $details = $this->builder([
            Config::XML_SD_DELIVERY_METHODS => "Standard | 2 | 5 | 4.5\r\n\r\nPickup\nCourier | 3",
            Config::XML_SD_SHIPPING_HANDLING_MIN => '1',
            Config::XML_SD_SHIPPING_HANDLING_MAX => '2',
        ])->build('USD', 1);

        $this->assertCount(3, $details);
        $this->assertSame('US', $details[0]['shippingDestination']['addressCountry']);
        $this->assertSame('4.50', $details[0]['shippingRate']['value']);
        $this->assertSame(['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 2, 'unitCode' => 'DAY'],
            $details[0]['deliveryTime']['handlingTime']);
        $this->assertSame(2, $details[0]['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(5, $details[0]['deliveryTime']['transitTime']['maxValue']);

        $this->assertSame('0.00', $details[1]['shippingRate']['value']);
        $this->assertSame(0, $details[1]['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(0, $details[1]['deliveryTime']['transitTime']['maxValue']);

        $this->assertSame(3, $details[2]['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(3, $details[2]['deliveryTime']['transitTime']['maxValue']);
    }
}
