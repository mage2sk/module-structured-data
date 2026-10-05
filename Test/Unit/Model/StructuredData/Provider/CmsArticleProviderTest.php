<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\App\Request\Http;
use Panth\StructuredData\Model\StructuredData\Provider\CmsArticleProvider;

class CmsArticleProviderTest extends AbstractProviderTestCase
{
    private function page(array $values): PageInterface
    {
        $page = $this->createStub(PageInterface::class);
        foreach ($values as $method => $value) {
            $page->method($method)->willReturn($value);
        }

        return $page;
    }

    private function provider(?PageInterface $page, string $action = 'cms_page_view'): CmsArticleProvider
    {
        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn($action);

        return new CmsArticleProvider(
            $this->registry($page !== null ? ['cms_page' => $page] : []),
            $request,
            $this->storeManager(),
            $this->config()
        );
    }

    public function testApplicability(): void
    {
        $page = $this->page(['getIdentifier' => 'about']);

        $this->assertSame('article', $this->provider(null)->getCode());
        $this->assertFalse($this->provider(null)->isApplicable());
        $this->assertFalse($this->provider($page, 'cms_index_index')->isApplicable());
        $this->assertTrue($this->provider($page)->isApplicable());
        $this->assertSame([], $this->provider(null)->getJsonLd());
    }

    public function testRegularPageBecomesWebPage(): void
    {
        $node = $this->provider($this->page([
            'getIdentifier' => 'about-us',
            'getTitle' => 'About',
            'getMetaDescription' => '  Who we are  ',
            'getUpdateTime' => '2024-03-04T05:06:07+00:00',
        ]))->getJsonLd();

        $this->assertSame([
            '@type' => 'WebPage',
            '@id' => 'https://example.com/about-us#webpage',
            'url' => 'https://example.com/about-us',
            'name' => 'About',
            'isPartOf' => ['@id' => 'https://example.com/#website'],
            'publisher' => ['@id' => 'https://example.com/#organization'],
            'description' => 'Who we are',
            'dateModified' => '2024-03-04T05:06:07+00:00',
        ], $node);
    }

    public function testWebPageWithoutTitleUsesIdentifierAndSkipsBadDates(): void
    {
        $node = $this->provider($this->page([
            'getIdentifier' => 'faq',
            'getTitle' => '',
            'getUpdateTime' => 'garbage date',
        ]))->getJsonLd();

        $this->assertSame('faq', $node['name']);
        $this->assertArrayNotHasKey('description', $node);
        $this->assertArrayNotHasKey('dateModified', $node);
    }

    public function testBlogPrefixedPageBecomesArticle(): void
    {
        $node = $this->provider($this->page([
            'getIdentifier' => 'blog/hello',
            'getTitle' => 'Hello',
            'getContent' => '<p>Body <b>text</b></p>',
            'getCreationTime' => '2024-01-01T00:00:00+00:00',
            'getUpdateTime' => '2024-01-02T00:00:00+00:00',
        ]))->getJsonLd();

        $this->assertSame('Article', $node['@type']);
        $this->assertSame('https://example.com/blog/hello#article', $node['@id']);
        $this->assertSame('Hello', $node['headline']);
        $this->assertSame('Body text', $node['description']);
        $this->assertSame('Body text', $node['articleBody']);
        $this->assertSame('https://example.com/blog/hello', $node['mainEntityOfPage']);
        $this->assertSame(['@id' => 'https://example.com/#organization'], $node['author']);
        $this->assertSame('2024-01-01T00:00:00+00:00', $node['datePublished']);
        $this->assertSame('2024-01-02T00:00:00+00:00', $node['dateModified']);
    }

    public function testKeywordsMarkArticleAndLongBodyIsTruncated(): void
    {
        $body = str_repeat('x', 6000);
        $node = $this->provider($this->page([
            'getIdentifier' => 'guide',
            'getTitle' => '',
            'getMetaKeywords' => 'Buying ARTICLE',
            'getMetaDescription' => 'Short desc',
            'getContent' => $body,
        ]))->getJsonLd();

        $this->assertSame('Article', $node['@type']);
        $this->assertSame('guide', $node['headline']);
        $this->assertSame('Short desc', $node['description']);
        $this->assertSame(5000, strlen($node['articleBody']));
        $this->assertArrayNotHasKey('datePublished', $node);
        $this->assertArrayNotHasKey('dateModified', $node);
    }

    public function testNewsAndArticlesPrefixesAreArticles(): void
    {
        foreach (['news/a', 'articles/b'] as $identifier) {
            $node = $this->provider($this->page(['getIdentifier' => $identifier, 'getTitle' => 'T']))->getJsonLd();
            $this->assertSame('Article', $node['@type'], $identifier);
        }
    }
}
