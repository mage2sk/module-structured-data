<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Panth\StructuredData\Model\StructuredData\MicrodataRemover;
use PHPUnit\Framework\TestCase;

class MicrodataRemoverTest extends TestCase
{
    public function testRemovesAttributesFromTagsOnly(): void
    {
        $html = '<div itemscope itemtype="https://schema.org/Product" x-show="qty > 0" class="a">'
            . '<span itemref="r" itemprop="name" title="a > b">Tent itemprop="name" in text</span></div>';

        $this->assertSame(
            '<div x-show="qty > 0" class="a"><span title="a > b">Tent itemprop="name" in text</span></div>',
            (new MicrodataRemover())->remove($html)
        );
    }

    public function testScriptsStylesTextareasAndCommentsAreUntouched(): void
    {
        $html = '<script>const itemId = this.item; let itemType = 1; var x = "<b itemprop=\"a\">";</script>'
            . '<style>.itemscope{}</style><textarea> itemprop="x"</textarea><!-- <i itemscope> -->'
            . '<i itemscope=""></i>';

        $this->assertSame(
            '<script>const itemId = this.item; let itemType = 1; var x = "<b itemprop=\"a\">";</script>'
            . '<style>.itemscope{}</style><textarea> itemprop="x"</textarea><!-- <i itemscope> -->'
            . '<i></i>',
            (new MicrodataRemover())->remove($html)
        );
    }

    public function testAttributeNamesThatOnlyStartWithItemAreKept(): void
    {
        $html = '<div itemscopex="1" data-itemprop="2" itemId="3"></div>';

        $this->assertSame($html, (new MicrodataRemover())->remove($html));
        $this->assertSame('', (new MicrodataRemover())->remove(''));
        $this->assertSame('<p>no markup</p>', (new MicrodataRemover())->remove('<p>no markup</p>'));
    }
}
