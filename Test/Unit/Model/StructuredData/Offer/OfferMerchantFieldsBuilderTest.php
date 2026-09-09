<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Offer;

use Magento\Catalog\Model\Product;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Offer\OfferMerchantFieldsBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OfferMerchantFieldsBuilderTest extends TestCase
{
    private function config(array $overrides = []): Config
    {
        $defaults = [
            'isMerchantFieldsEnabled' => true,
            'isMerchantReturnEnabled' => true,
            'isMerchantShippingEnabled' => true,
            'getReturnApplicableCountry' => 'US',
            'getShippingCountry' => 'US',
            'getReturnPolicyDays' => 30,
            'getReturnMethodSchemaUrl' => 'https://schema.org/ReturnByMail',
            'getReturnFeesSchemaUrl' => 'https://schema.org/FreeReturn',
            'getShippingDefaultRate' => '0.00',
            'getShippingHandlingMin' => 0,
            'getShippingHandlingMax' => 1,
            'getShippingTransitMin' => 1,
            'getShippingTransitMax' => 5,
        ];

        $mock = $this->createStub(Config::class);
        foreach (array_merge($defaults, $overrides) as $method => $value) {
            if ($value instanceof \Throwable) {
                $mock->method($method)->willThrowException($value);
                continue;
            }
            $mock->method($method)->willReturn($value);
        }

        return $mock;
    }

    private function product(string $typeId): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn($typeId);

        return $product;
    }

    public function testPhysicalProductGetsFiniteReturnWindowAndShipping(): void
    {
        $builder = new OfferMerchantFieldsBuilder($this->config());

        $offer = $builder->apply(['@type' => 'Offer'], $this->product('simple'), 'USD', 1);

        $this->assertSame(
            [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'US',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => 30,
                'returnMethod' => 'https://schema.org/ReturnByMail',
                'returnFees' => 'https://schema.org/FreeReturn',
            ],
            $offer['hasMerchantReturnPolicy']
        );
        $this->assertSame('OfferShippingDetails', $offer['shippingDetails']['@type']);
        $this->assertSame('0.00', $offer['shippingDetails']['shippingRate']['value']);
        $this->assertSame('USD', $offer['shippingDetails']['shippingRate']['currency']);
        $this->assertSame('US', $offer['shippingDetails']['shippingDestination']['addressCountry']);
        $this->assertSame(
            ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
            $offer['shippingDetails']['deliveryTime']['handlingTime']
        );
        $this->assertSame(
            ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 5, 'unitCode' => 'DAY'],
            $offer['shippingDetails']['deliveryTime']['transitTime']
        );
    }

    #[DataProvider('digitalTypeProvider')]
    public function testDigitalProductGetsNotPermittedPolicyAndZeroShipping(string $typeId): void
    {
        $builder = new OfferMerchantFieldsBuilder($this->config());

        $offer = $builder->apply(['@type' => 'Offer'], $this->product($typeId), 'EUR', 1);

        $this->assertSame(
            'https://schema.org/MerchantReturnNotPermitted',
            $offer['hasMerchantReturnPolicy']['returnPolicyCategory']
        );
        $this->assertArrayNotHasKey('merchantReturnDays', $offer['hasMerchantReturnPolicy']);
        $this->assertSame('0.00', $offer['shippingDetails']['shippingRate']['value']);
        $this->assertSame('EUR', $offer['shippingDetails']['shippingRate']['currency']);
        $this->assertSame(0, $offer['shippingDetails']['deliveryTime']['transitTime']['maxValue']);
    }

    public static function digitalTypeProvider(): array
    {
        return [
            'virtual' => ['virtual'],
            'downloadable' => ['downloadable'],
        ];
    }

    public function testZeroReturnWindowYieldsNotPermittedPolicy(): void
    {
        $builder = new OfferMerchantFieldsBuilder($this->config(['getReturnPolicyDays' => 0]));

        $offer = $builder->apply(['@type' => 'Offer'], $this->product('simple'), 'USD', 1);

        $this->assertSame(
            'https://schema.org/MerchantReturnNotPermitted',
            $offer['hasMerchantReturnPolicy']['returnPolicyCategory']
        );
    }

    public function testMasterToggleOffLeavesOfferUntouched(): void
    {
        $builder = new OfferMerchantFieldsBuilder($this->config(['isMerchantFieldsEnabled' => false]));

        $offer = $builder->apply(['@type' => 'Offer'], $this->product('simple'), 'USD', 1);

        $this->assertSame(['@type' => 'Offer'], $offer);
    }

    public function testSubTogglesAreIndependent(): void
    {
        $returnOnly = new OfferMerchantFieldsBuilder($this->config(['isMerchantShippingEnabled' => false]));
        $offer = $returnOnly->apply(['@type' => 'Offer'], $this->product('simple'), 'USD', 1);
        $this->assertArrayHasKey('hasMerchantReturnPolicy', $offer);
        $this->assertArrayNotHasKey('shippingDetails', $offer);

        $shippingOnly = new OfferMerchantFieldsBuilder($this->config(['isMerchantReturnEnabled' => false]));
        $offer = $shippingOnly->apply(['@type' => 'Offer'], $this->product('simple'), 'USD', 1);
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $offer);
        $this->assertArrayHasKey('shippingDetails', $offer);
    }

    public function testExistingOfferKeysAreNotOverwritten(): void
    {
        $builder = new OfferMerchantFieldsBuilder($this->config());
        $existing = [
            '@type' => 'Offer',
            'hasMerchantReturnPolicy' => ['@type' => 'MerchantReturnPolicy', 'applicableCountry' => 'GB'],
            'shippingDetails' => ['@type' => 'OfferShippingDetails', 'shippingRate' => 'kept'],
        ];

        $offer = $builder->apply($existing, $this->product('simple'), 'USD', 1);

        $this->assertSame($existing, $offer);
    }

    public function testMaxValueNeverFallsBelowMinValue(): void
    {
        $builder = new OfferMerchantFieldsBuilder($this->config([
            'getShippingHandlingMin' => 3,
            'getShippingHandlingMax' => 1,
            'getShippingTransitMin' => 9,
            'getShippingTransitMax' => 2,
        ]));

        $offer = $builder->apply(['@type' => 'Offer'], $this->product('simple'), 'USD', 1);

        $this->assertSame(3, $offer['shippingDetails']['deliveryTime']['handlingTime']['maxValue']);
        $this->assertSame(9, $offer['shippingDetails']['deliveryTime']['transitTime']['maxValue']);
    }

    public function testFailsOpenWhenConfigThrows(): void
    {
        $builder = new OfferMerchantFieldsBuilder($this->config([
            'getReturnApplicableCountry' => new \RuntimeException('config blew up'),
        ]));

        $offer = $builder->apply(['@type' => 'Offer', 'price' => '19.99'], $this->product('simple'), 'USD', 1);

        $this->assertSame(['@type' => 'Offer', 'price' => '19.99'], $offer);
    }
}
