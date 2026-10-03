<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Panth\StructuredData\Model\StructuredData\Provider\ProsConsProvider;

class ProsConsProviderTest extends AbstractProviderTestCase
{
    private const FLAG = 'panth_structured_data/structured_data/pros_cons_enabled';

    private function provider(array $registry, array $values = [], array $flags = []): ProsConsProvider
    {
        return new ProsConsProvider(
            $this->registry($registry),
            $this->request(),
            $this->storeManager(),
            $this->config($values, $flags)
        );
    }

    public function testApplicabilityNeedsProductAndFlag(): void
    {
        $product = $this->product();

        $this->assertSame('pros_cons', $this->provider([])->getCode());
        $this->assertFalse($this->provider([], [], [self::FLAG => true])->isApplicable());
        $this->assertFalse($this->provider(['current_product' => $product])->isApplicable());
        $this->assertTrue($this->provider(['current_product' => $product], [], [self::FLAG => true])->isApplicable());
    }

    public function testNoProductGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([])->getJsonLd());
    }

    public function testDefaultAttributesProduceTrimmedItemLists(): void
    {
        $product = $this->product(
            ['getProductUrl' => 'https://example.com/p.html'],
            [
                'product_pros' => "  <b>Light</b>  \r\n\r\nDurable\n",
                'product_cons' => 'Pricey',
            ]
        );

        $node = $this->provider(['current_product' => $product])->getJsonLd();

        $this->assertSame('https://example.com/p.html#product', $node['@id']);
        $this->assertSame('ItemList', $node['positiveNotes']['@type']);
        $this->assertSame(
            [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Light'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Durable'],
            ],
            $node['positiveNotes']['itemListElement']
        );
        $this->assertSame('Pricey', $node['negativeNotes']['itemListElement'][0]['name']);
    }

    public function testConfiguredAttributesAreUsedAndEmptySideIsOmitted(): void
    {
        $product = $this->product(
            ['getProductUrl' => 'https://example.com/p.html'],
            ['my_cons' => 'Heavy', 'product_pros' => 'Ignored']
        );

        $node = $this->provider(['current_product' => $product], [
            'panth_structured_data/structured_data/pros_attribute' => 'my_pros',
            'panth_structured_data/structured_data/cons_attribute' => 'my_cons',
        ])->getJsonLd();

        $this->assertArrayNotHasKey('positiveNotes', $node);
        $this->assertSame('Heavy', $node['negativeNotes']['itemListElement'][0]['name']);
    }

    public function testNoNotesGivesNoNode(): void
    {
        $product = $this->product([], ['product_pros' => "  \n ", 'product_cons' => '<p></p>']);

        $this->assertSame([], $this->provider(['current_product' => $product])->getJsonLd());
    }
}
