<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Plugin\StructuredData;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Plugin\StructuredData\RemoveNativeMarkupPlugin;
use PHPUnit\Framework\TestCase;

class RemoveNativeMarkupPluginTest extends TestCase
{
    private function plugin(bool $moduleEnabled = true, bool $removeEnabled = true): RemoveNativeMarkupPlugin
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path) => $path === 'panth_structured_data/structured_data/remove_native_markup'
                && $removeEnabled
        );
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($moduleEnabled);

        return new RemoveNativeMarkupPlugin($scopeConfig, $config);
    }

    private function block(string $name): AbstractBlock
    {
        $block = $this->createStub(AbstractBlock::class);
        $block->method('getNameInLayout')->willReturn($name);

        return $block;
    }

    public function testStripsNativeJsonLdAndMicrodataFromTargetBlocks(): void
    {
        $html = '<div itemscope itemtype="https://schema.org/Product">'
            . '<script type="application/ld+json">{"@type":"Product"}</script>'
            . '<script data-panth-seo type=\'application/ld+json\'>{"own":1}</script>'
            . '<span itemprop=\'name\'>Bag</span><meta itemprop=price content="1">'
            . '<script type="text/javascript">var a = 1;</script>'
            . '</div>';

        $result = $this->plugin()->afterToHtml($this->block('product.info.main'), $html);

        $this->assertSame(
            '<div>'
            . '<script data-panth-seo type=\'application/ld+json\'>{"own":1}</script>'
            . '<span>Bag</span><meta content="1">'
            . '<script type="text/javascript">var a = 1;</script>'
            . '</div>',
            $result
        );
    }

    public function testOtherBlocksAndEmptyOutputAreUntouched(): void
    {
        $html = '<div itemscope><script type="application/ld+json">{}</script></div>';

        $this->assertSame($html, $this->plugin()->afterToHtml($this->block('footer'), $html));
        $this->assertNull($this->plugin()->afterToHtml($this->block('breadcrumbs'), null));
        $this->assertSame('', $this->plugin()->afterToHtml($this->block('breadcrumbs'), ''));
    }

    public function testDisabledFeatureLeavesMarkup(): void
    {
        $html = '<ul itemscope><li itemprop="itemListElement">Home</li></ul>';

        $this->assertSame($html, $this->plugin(false, true)->afterToHtml($this->block('breadcrumbs'), $html));
        $this->assertSame($html, $this->plugin(true, false)->afterToHtml($this->block('breadcrumbs'), $html));
        $this->assertSame(
            '<ul><li>Home</li></ul>',
            $this->plugin()->afterToHtml($this->block('breadcrumbs'), $html)
        );
    }

    public function testBlockConsistingOnlyOfNativeJsonLdIsStripped(): void
    {
        $html = '<script type="application/ld+json">{"@type":"Offer"}</script>';

        $this->assertSame('', $this->plugin()->afterToHtml($this->block('product.price.final'), $html));
    }

    public function testBlockConsistingOnlyOfOwnJsonLdIsKept(): void
    {
        $html = '<script data-panth-seo type="application/ld+json">{"@type":"Offer"}</script>';

        $this->assertSame($html, $this->plugin()->afterToHtml($this->block('product.price.final'), $html));
    }
}
