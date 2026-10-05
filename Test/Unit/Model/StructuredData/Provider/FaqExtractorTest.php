<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Category;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\View\LayoutInterface;
use Panth\Faq\Block\Schema as FaqSchemaBlock;
use Panth\StructuredData\Model\StructuredData\Provider\FaqExtractor;

class FaqExtractorTest extends AbstractProviderTestCase
{
    private const FAQ_HTML = '<h2>Intro</h2><p>Not a question</p>'
        . '<h2>Do you ship?</h2><p>Yes, <b>worldwide</b>.</p><p>Second paragraph</p>'
        . '<h3>Can I return?</h3>  <span>Within</span> <span>30 days</span><ul><li>Unused</li></ul>'
        . '<h4>Empty?</h4><h2>Next</h2>';

    private function provider(
        array $registry,
        bool $faqModule = false,
        string $route = 'cms',
        ?LayoutInterface $layout = null
    ): FaqExtractor {
        $moduleManager = $this->createStub(ModuleManager::class);
        $moduleManager->method('isEnabled')->willReturn($faqModule);
        $request = $this->createStub(Http::class);
        $request->method('getRouteName')->willReturn($route);
        $request->method('getPathInfo')->willReturn('/faq-page');

        return new FaqExtractor(
            $this->registry($registry),
            $request,
            $this->storeManager(),
            $this->config(),
            $moduleManager,
            $layout ?? $this->createStub(LayoutInterface::class)
        );
    }

    private function cmsPage(string $content): PageInterface
    {
        $page = $this->createStub(PageInterface::class);
        $page->method('getContent')->willReturn($content);

        return $page;
    }

    public function testParsesQuestionHeadingsFromCmsContent(): void
    {
        $provider = $this->provider(['cms_page' => $this->cmsPage(self::FAQ_HTML)]);

        $node = $provider->getJsonLd();

        $this->assertSame('faq', $provider->getCode());
        $this->assertSame('FAQPage', $node['@type']);
        $this->assertSame('https://example.com/#faq-' . sha1('/faq-page'), $node['@id']);
        $this->assertSame([
            [
                '@type' => 'Question',
                'name' => 'Do you ship?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes, worldwide.'],
            ],
            [
                '@type' => 'Question',
                'name' => 'Can I return?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Within 30 days Unused'],
            ],
        ], $node['mainEntity']);
    }

    public function testProductDescriptionIsPreferred(): void
    {
        $product = $this->product([], ['description' => self::FAQ_HTML]);

        $node = $this->provider([
            'current_product' => $product,
            'cms_page' => $this->cmsPage('<h2>Ignored?</h2><p>x</p>'),
        ])->getJsonLd();

        $this->assertCount(2, $node['mainEntity']);
    }

    public function testCategoryDescriptionIsUsedLast(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getData')->willReturnCallback(
            static fn($key = '') => $key === 'description' ? self::FAQ_HTML : null
        );

        $this->assertCount(2, $this->provider(['current_category' => $category])->getJsonLd()['mainEntity']);
    }

    public function testSingleQuestionPlainTextOrNoContextGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([])->getJsonLd());
        $this->assertSame([], $this->provider(['cms_page' => $this->cmsPage('Plain? text? only?')])->getJsonLd());
        $this->assertSame(
            [],
            $this->provider(['cms_page' => $this->cmsPage('<h2>One?</h2><p>Only one</p>')])->getJsonLd()
        );
        $this->assertSame([], $this->provider(['current_product' => $this->product()])->getJsonLd());
    }

    public function testPanthFaqRouteOwnsThePage(): void
    {
        $registry = ['cms_page' => $this->cmsPage(self::FAQ_HTML)];

        $this->assertSame([], $this->provider($registry, true, 'faq')->getJsonLd());
        $this->assertNotSame([], $this->provider($registry, true, 'cms')->getJsonLd());
    }

    public function testLayoutLookupFailureDoesNotSuppress(): void
    {
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getBlock')->willThrowException(new \RuntimeException('layout'));

        $node = $this->provider(['cms_page' => $this->cmsPage(self::FAQ_HTML)], true, 'cms', $layout)->getJsonLd();

        $this->assertSame('FAQPage', $node['@type']);
    }

    public function testPanthFaqSchemaBlockOwnsThePageOnlyWhenItRendersData(): void
    {
        if (!class_exists(FaqSchemaBlock::class)) {
            $this->markTestSkipped('Panth_Faq is not installed');
        }
        $registry = ['cms_page' => $this->cmsPage(self::FAQ_HTML)];
        $layoutWith = function (string $data, bool $enabled = true): LayoutInterface {
            $block = $this->createStub(FaqSchemaBlock::class);
            $block->method('isEnabled')->willReturn($enabled);
            $block->method('getSchemaData')->willReturn($data);
            $layout = $this->createStub(LayoutInterface::class);
            $layout->method('getBlock')->willReturn($block);
            return $layout;
        };

        $this->assertSame([], $this->provider($registry, true, 'cms', $layoutWith('{"@type":"FAQPage"}'))->getJsonLd());
        $this->assertNotSame([], $this->provider($registry, true, 'cms', $layoutWith(''))->getJsonLd());
        $this->assertNotSame([], $this->provider($registry, true, 'cms', $layoutWith('{}', false))->getJsonLd());
    }
}
