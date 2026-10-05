<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\StructuredData\Model\Config\Source\BreadcrumbFormat;
use Panth\StructuredData\Model\Config\Source\BusinessType;
use Panth\StructuredData\Model\Config\Source\ProductCondition;
use Panth\StructuredData\Model\Config\Source\ReturnMethod;
use Panth\StructuredData\Model\Config\Source\ReturnPolicyType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OptionSourcesTest extends TestCase
{
    #[DataProvider('sourceProvider')]
    public function testOptionsExposeExpectedValuesAndLabels(string $class, array $expected): void
    {
        /** @var OptionSourceInterface $source */
        $source = new $class();
        $options = $source->toOptionArray();

        $actual = [];
        foreach ($options as $option) {
            $actual[$option['value']] = (string) $option['label'];
        }

        $this->assertSame($expected, $actual);
    }

    public static function sourceProvider(): array
    {
        return [
            'breadcrumb format' => [BreadcrumbFormat::class, [
                'shortest' => 'Shortest Path',
                'longest' => 'Longest Path / Deepest Category',
            ]],
            'business type' => [BusinessType::class, [
                'Organization' => 'Organization',
                'LocalBusiness' => 'Local Business',
                'Store' => 'Store',
                'OnlineStore' => 'Online Store',
            ]],
            'product condition' => [ProductCondition::class, [
                'new' => 'New',
                'used' => 'Used',
                'refurbished' => 'Refurbished',
                'damaged' => 'Damaged',
            ]],
            'return method' => [ReturnMethod::class, [
                'bymail' => 'Return by mail',
                'instore' => 'Return in store',
                'kiosk' => 'Return at kiosk',
            ]],
            'return policy type' => [ReturnPolicyType::class, [
                'refund' => 'Refund',
                'exchange' => 'Exchange',
            ]],
        ];
    }
}
