<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\Blog\BlogDetector;
use Panth\StructuredData\Model\StructuredData\Provider\BlogPostProvider;

class BlogPostProviderTest extends AbstractProviderTestCase
{
    private function provider(
        string $action,
        array $registry = [],
        array $params = [],
        ?BlogDetector $detector = null,
        string $currentUrl = 'https://example.com/blog/post/view/id/5?utm=x',
        ?StoreManagerInterface $storeManager = null
    ): BlogPostProvider {
        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn($action);
        $request->method('getParam')->willReturnCallback(static fn(string $key) => $params[$key] ?? null);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getCurrentUrl')->willReturn($currentUrl);

        return new BlogPostProvider(
            $this->registry($registry),
            $request,
            $storeManager ?? $this->storeManager(),
            $this->config(),
            $detector ?? $this->createStub(BlogDetector::class),
            $url
        );
    }

    public function testNotApplicableOutsidePostView(): void
    {
        $provider = $this->provider('cms_page_view', ['current_blog_post' => new DataObject(['title' => 'x'])]);

        $this->assertSame('article', $provider->getCode());
        $this->assertFalse($provider->isApplicable());
        $this->assertSame([], $provider->getJsonLd());
    }

    public function testPostFromRegistryBuildsBlogPosting(): void
    {
        $post = new class ([
            'title' => ' Hello ',
            'content' => '<p>Long <b>body</b></p>',
            'featured_img' => '/blog/hello.jpg',
            'publish_time' => '2024-02-03T04:05:06+00:00',
            'updated_at' => '2024-02-04T00:00:00+00:00',
        ]) extends DataObject {
            public function getPostUrl(): string
            {
                return 'relative/url';
            }

            public function getUrl(): string
            {
                return 'https://example.com/blog/hello';
            }
        };

        $provider = $this->provider('MPBLOG_POST_VIEW', ['mp_current_post' => $post]);

        $this->assertTrue($provider->isApplicable());
        $this->assertSame([
            '@type' => 'BlogPosting',
            '@id' => 'https://example.com/blog/hello#article',
            'headline' => 'Hello',
            'mainEntityOfPage' => 'https://example.com/blog/hello',
            'url' => 'https://example.com/blog/hello',
            'author' => ['@id' => 'https://example.com/#organization'],
            'publisher' => ['@id' => 'https://example.com/#organization'],
            'description' => 'Long body',
            'articleBody' => 'Long body',
            'image' => 'https://example.com/media/blog/hello.jpg',
            'datePublished' => '2024-02-03T04:05:06+00:00',
            'dateModified' => '2024-02-04T00:00:00+00:00',
        ], $provider->getJsonLd());
    }

    public function testPostLoadedByIdWithCurrentUrlAndAbsoluteImage(): void
    {
        $post = new DataObject([
            'name' => 'Named post',
            'meta_description' => 'Meta',
            'image' => 'https://cdn.test/i.png',
            'created_at' => 'nonsense',
        ]);
        $detector = $this->createMock(BlogDetector::class);
        $detector->expects($this->once())
            ->method('getPostById')
            ->with('blog_post_view', 5)
            ->willReturn($post);

        $provider = $this->provider('blog_post_view', [], ['post_id' => '5'], $detector);

        $node = $provider->getJsonLd();
        $provider->getJsonLd();

        $this->assertSame('Named post', $node['headline']);
        $this->assertSame('https://example.com/blog/post/view/id/5', $node['url']);
        $this->assertSame('Meta', $node['description']);
        $this->assertSame('https://cdn.test/i.png', $node['image']);
        $this->assertArrayNotHasKey('articleBody', $node);
        $this->assertArrayNotHasKey('datePublished', $node);
        $this->assertArrayNotHasKey('dateModified', $node);
    }

    public function testThrowingUrlMethodsFallBackToCurrentUrlWithoutQuery(): void
    {
        $post = new class (['title' => 'T', 'featured_image' => 'img.png']) extends DataObject {
            public function getPostUrl(): string
            {
                throw new \RuntimeException('url');
            }
        };

        $node = $this->provider(
            'blog_post_view',
            ['current_post' => $post],
            [],
            null,
            'https://example.com/blog/t',
            $this->failingStoreManager()
        )->getJsonLd();

        $this->assertSame('https://example.com/blog/t', $node['url']);
        $this->assertArrayNotHasKey('image', $node);
        $this->assertArrayNotHasKey('description', $node);
    }

    public function testMissingPostOrHeadlineGivesNoNode(): void
    {
        $detector = $this->createStub(BlogDetector::class);
        $detector->method('getPostById')->willReturn(new DataObject(['title' => ['not scalar']]));

        $this->assertSame([], $this->provider('blog_post_view')->getJsonLd());
        $this->assertSame([], $this->provider('blog_post_view', ['current_blog_post' => new \stdClass()])->getJsonLd());
        $this->assertSame([], $this->provider('blog_post_view', [], ['id' => '7'], $detector)->getJsonLd());
    }

    public function testPlainRequestWithoutActionNameIsNotApplicable(): void
    {
        $provider = new BlogPostProvider(
            $this->registry(['current_blog_post' => new DataObject(['title' => 'x'])]),
            $this->request(),
            $this->storeManager(),
            $this->config(),
            $this->createStub(BlogDetector::class),
            $this->createStub(UrlInterface::class)
        );

        $this->assertFalse($provider->isApplicable());
    }
}
