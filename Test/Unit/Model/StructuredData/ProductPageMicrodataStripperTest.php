<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\MicrodataRemover;
use Panth\StructuredData\Model\StructuredData\ProductPageMicrodataStripper;
use PHPUnit\Framework\TestCase;

class ProductPageMicrodataStripperTest extends TestCase
{
    private function stripper(
        string $action = 'catalog_product_view',
        bool $moduleEnabled = true,
        bool $productEnabled = true,
        bool $removeEnabled = true,
        ?RequestInterface $request = null
    ): ProductPageMicrodataStripper {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path) => $path === 'panth_structured_data/structured_data/remove_native_markup'
                && $removeEnabled
        );
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($moduleEnabled);
        $config->method('isStructuredDataEnabled')->willReturnCallback(
            static fn(string $code) => $code === 'product' && $productEnabled
        );
        if ($request === null) {
            $request = $this->createStub(Http::class);
            $request->method('getFullActionName')->willReturn($action);
        }

        return new ProductPageMicrodataStripper($scopeConfig, $config, $request, new MicrodataRemover());
    }

    public function testActiveOnlyOnProductPageWithAllFlagsOn(): void
    {
        $this->assertTrue($this->stripper()->isActive());
        $this->assertTrue($this->stripper('Catalog_Product_View')->isActive());
        $this->assertFalse($this->stripper('catalog_category_view')->isActive());
        $this->assertFalse($this->stripper('catalog_product_view', false)->isActive());
        $this->assertFalse($this->stripper('catalog_product_view', true, false)->isActive());
        $this->assertFalse($this->stripper('catalog_product_view', true, true, false)->isActive());
        $this->assertFalse(
            $this->stripper('catalog_product_view', true, true, true, $this->createStub(RequestInterface::class))
                ->isActive()
        );
    }

    public function testStripsAllMicrodataAttributeForms(): void
    {
        $html = '<div itemscope itemtype="https://schema.org/Product" class="a">'
            . '<div itemprop="offers" itemscope="" itemtype=\'https://schema.org/Offer\'>'
            . '<meta itemprop=price content="9"><span itemid="#x" itemprop="name">Tent</span>'
            . '<i itemscope="itemscope"></i><b itemscope/></div></div>';

        $this->assertSame(
            '<div class="a"><div><meta content="9"><span>Tent</span><i></i><b/></div></div>',
            $this->stripper()->strip($html)
        );
    }

    public function testLeavesUnrelatedMarkupAndEmptyInputAlone(): void
    {
        $html = '<p class="items">Item list</p><script type="application/ld+json">{"a":1}</script>';

        $this->assertSame($html, $this->stripper()->strip($html));
        $this->assertSame('', $this->stripper()->strip(''));
        $this->assertSame('<p>plain</p>', $this->stripper()->strip('<p>plain</p>'));
    }
}
