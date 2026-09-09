<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\StructuredData\Helper\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function configWith(string $path, mixed $value): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $requested) => $requested === $path ? $value : null
        );

        return new Config($scopeConfig);
    }

    #[DataProvider('returnFeesProvider')]
    public function testReturnFeesMapping(mixed $stored, string $expected): void
    {
        $config = $this->configWith(Config::XML_SD_RETURN_FEES, $stored);

        $this->assertSame($expected, $config->getReturnFeesSchemaUrl(1));
    }

    public static function returnFeesProvider(): array
    {
        return [
            'unset' => [null, 'https://schema.org/FreeReturn'],
            'blank' => ['', 'https://schema.org/FreeReturn'],
            'free' => ['free', 'https://schema.org/FreeReturn'],
            'freereturn' => ['FreeReturn', 'https://schema.org/FreeReturn'],
            'customer responsibility token' => [
                'ReturnFeesCustomerResponsibility',
                'https://schema.org/ReturnFeesCustomerResponsibility',
            ],
            'shipping fees token' => ['returnshippingfees', 'https://schema.org/ReturnShippingFees'],
            'restocking token' => ['RestockingFees', 'https://schema.org/RestockingFees'],
            'custom text falls back to customer responsibility' => [
                'restocking',
                'https://schema.org/ReturnFeesCustomerResponsibility',
            ],
            'descriptive text falls back to customer responsibility' => [
                '10% restocking fee applies',
                'https://schema.org/ReturnFeesCustomerResponsibility',
            ],
        ];
    }

    #[DataProvider('returnMethodProvider')]
    public function testReturnMethodMapping(mixed $stored, string $expected): void
    {
        $config = $this->configWith(Config::XML_SD_RETURN_METHOD, $stored);

        $this->assertSame($expected, $config->getReturnMethodSchemaUrl(1));
    }

    public static function returnMethodProvider(): array
    {
        return [
            'unset' => [null, 'https://schema.org/ReturnByMail'],
            'bymail' => ['bymail', 'https://schema.org/ReturnByMail'],
            'instore' => ['InStore', 'https://schema.org/ReturnInStore'],
            'kiosk' => ['kiosk', 'https://schema.org/ReturnAtKiosk'],
            'unknown' => ['carrier-pigeon', 'https://schema.org/ReturnByMail'],
        ];
    }

    public function testReturnAndShippingCountriesFallBackToStoreCountry(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $path === Config::XML_STORE_COUNTRY ? 'GB' : ''
        );
        $config = new Config($scopeConfig);

        $this->assertSame('GB', $config->getReturnApplicableCountry(1));
        $this->assertSame('GB', $config->getShippingCountry(1));
    }

    public function testExplicitCountriesWinOverStoreCountry(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => match ($path) {
                Config::XML_STORE_COUNTRY => 'GB',
                Config::XML_SD_SHIPPING_COUNTRY => 'de',
                default => '',
            }
        );
        $config = new Config($scopeConfig);

        $this->assertSame('GB', $config->getReturnApplicableCountry(1));
        $this->assertSame('DE', $config->getShippingCountry(1));
    }

    public function testShippingRateIsFormattedAsAmount(): void
    {
        $config = $this->configWith(Config::XML_SD_SHIPPING_DEFAULT_RATE, '4.9');

        $this->assertSame('4.90', $config->getShippingDefaultRate(1));
    }

    public function testNegativeDayValuesClampToZero(): void
    {
        $config = $this->configWith(Config::XML_SD_SHIPPING_HANDLING_MIN, '-3');

        $this->assertSame(0, $config->getShippingHandlingMin(1));
    }
}
