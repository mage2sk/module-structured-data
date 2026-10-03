<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\EnergyLabelProvider;

class EnergyLabelProviderTest extends AbstractProviderTestCase
{
    private const ENABLED = 'panth_structured_data/structured_data/energy_label_enabled';
    private const ATTRIBUTE = 'panth_structured_data/structured_data/energy_class_attribute';

    private function provider(
        array $registry,
        array $values = [],
        array $flags = [],
        ?StoreManagerInterface $storeManager = null
    ): EnergyLabelProvider {
        return new EnergyLabelProvider(
            $this->registry($registry),
            $this->request(),
            $storeManager ?? $this->storeManager(),
            $this->config(),
            $this->scopeConfig($values, $flags)
        );
    }

    private function productWith(array $data, mixed $attributeText = false): array
    {
        $text = $attributeText instanceof \Closure ? $attributeText : static fn() => $attributeText;

        return ['current_product' => $this->product(
            ['getProductUrl' => 'https://example.com/fridge.html', 'getAttributeText' => $text],
            $data
        )];
    }

    public function testApplicability(): void
    {
        $this->assertSame('energy_label_enabled', $this->provider([])->getCode());
        $this->assertFalse($this->provider([], [], [self::ENABLED => true])->isApplicable());
        $this->assertFalse($this->provider($this->productWith([]))->isApplicable());
        $this->assertTrue($this->provider($this->productWith([]), [], [self::ENABLED => true])->isApplicable());
        $this->assertTrue(
            $this->provider($this->productWith([]), [], [self::ENABLED => true], $this->failingStoreManager())
                ->isApplicable()
        );
    }

    public function testRawValuesAreNormalisedToSchemaUrls(): void
    {
        $node = $this->provider($this->productWith([
            'energy_class' => ' a++ ',
            'energy_scale_min' => 'G',
            'energy_scale_max' => 'A+++',
        ]))->getJsonLd();

        $this->assertSame('https://example.com/fridge.html#product', $node['@id']);
        $this->assertSame([
            '@type' => 'EnergyConsumptionDetails',
            'hasEnergyEfficiencyCategory' => 'https://schema.org/EUEnergyEfficiencyCategoryA2Plus',
            'energyEfficiencyScaleMin' => 'https://schema.org/EUEnergyEfficiencyCategoryG',
            'energyEfficiencyScaleMax' => 'https://schema.org/EUEnergyEfficiencyCategoryA3Plus',
        ], $node['hasEnergyConsumptionDetails']);
    }

    public function testAttributeTextWinsAndInvalidScaleIsOmitted(): void
    {
        $text = static fn(string $code) => $code === 'eclass' ? 'B' : false;

        $node = $this->provider(
            $this->productWith(['eclass' => '17', 'energy_scale_min' => 'Z'], $text),
            [self::ATTRIBUTE => 'eclass'],
            [],
            $this->failingStoreManager()
        )->getJsonLd();

        $details = $node['hasEnergyConsumptionDetails'];
        $this->assertSame('https://schema.org/EUEnergyEfficiencyCategoryB', $details['hasEnergyEfficiencyCategory']);
        $this->assertArrayNotHasKey('energyEfficiencyScaleMin', $details);
        $this->assertArrayNotHasKey('energyEfficiencyScaleMax', $details);
    }

    public function testAttributeTextExceptionFallsBackToRawData(): void
    {
        $text = static function (): never {
            throw new \RuntimeException('eav');
        };

        $node = $this->provider($this->productWith(['energy_class' => 'c'], $text))->getJsonLd();

        $this->assertSame(
            'https://schema.org/EUEnergyEfficiencyCategoryC',
            $node['hasEnergyConsumptionDetails']['hasEnergyEfficiencyCategory']
        );
    }

    public function testMissingOrUnknownClassGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([])->getJsonLd());
        $this->assertSame([], $this->provider($this->productWith([]))->getJsonLd());
        $this->assertSame([], $this->provider($this->productWith(['energy_class' => 12]))->getJsonLd());
        $this->assertSame([], $this->provider($this->productWith(['energy_class' => 'H']))->getJsonLd());
    }
}
