<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Plugin\StructuredData;

use Magento\Framework\View\Layout;
use Magento\Framework\View\Page\Config\Renderer;
use Panth\StructuredData\Model\StructuredData\ProductPageMicrodataStripper;
use Panth\StructuredData\Plugin\StructuredData\RemoveBodyMicrodataPlugin;
use Panth\StructuredData\Plugin\StructuredData\RemoveProductPageMicrodataPlugin;
use PHPUnit\Framework\TestCase;

class ProductPageMicrodataPluginsTest extends TestCase
{
    private function stripper(bool $active): ProductPageMicrodataStripper
    {
        $stripper = $this->createStub(ProductPageMicrodataStripper::class);
        $stripper->method('isActive')->willReturn($active);
        $stripper->method('strip')->willReturnCallback(
            static fn(string $html) => (string) preg_replace(
                '/\s+(?:itemscope="itemscope"|itemtype="[^"]*")/',
                '',
                $html
            )
        );

        return $stripper;
    }

    public function testLayoutOutputIsStrippedOnlyWhenActive(): void
    {
        $layout = $this->createStub(Layout::class);
        $html = '<div itemtype="https://schema.org/Offer">x</div>';
        $active = new RemoveProductPageMicrodataPlugin($this->stripper(true));
        $inactive = new RemoveProductPageMicrodataPlugin($this->stripper(false));

        $this->assertSame('<div>x</div>', $active->afterGetOutput($layout, $html));
        $this->assertSame($html, $inactive->afterGetOutput($layout, $html));
        $this->assertSame('', $active->afterGetOutput($layout, ''));
        $this->assertNull($active->afterGetOutput($layout, null));
    }

    public function testBodyAttributesLoseMicrodataOnlyForBodyWhenActive(): void
    {
        $renderer = $this->createStub(Renderer::class);
        $attrs = 'id="html-body" itemtype="https://schema.org/Product" itemscope="itemscope" class="catalog-product-view"';
        $leading = 'itemtype="https://schema.org/Product" itemscope="itemscope" class="x"';
        $active = new RemoveBodyMicrodataPlugin($this->stripper(true));
        $inactive = new RemoveBodyMicrodataPlugin($this->stripper(false));

        $this->assertSame(
            'id="html-body" class="catalog-product-view"',
            $active->afterRenderElementAttributes($renderer, $attrs, 'body')
        );
        $this->assertSame('class="x"', $active->afterRenderElementAttributes($renderer, $leading, 'body'));
        $this->assertSame($attrs, $active->afterRenderElementAttributes($renderer, $attrs, 'html'));
        $this->assertSame($attrs, $inactive->afterRenderElementAttributes($renderer, $attrs, 'body'));
        $this->assertSame('', $active->afterRenderElementAttributes($renderer, '', 'body'));
    }
}
