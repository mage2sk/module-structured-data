<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\Blog;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\Blog\BlogDetector;
use Panth\StructuredData\Test\Unit\Model\Blog\Fixture\Post;
use Panth\StructuredData\Test\Unit\Model\Blog\Fixture\ResourceModel\Post\Collection;
use Panth\StructuredData\Test\Unit\Model\Blog\Fixture\ResourceModel\Post\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BlogDetectorTest extends TestCase
{
    private function detector(
        array $supported,
        ?CollectionFactory $factory = null,
        array $columns = [],
        ?LoggerInterface $logger = null,
        ?StoreManagerInterface $storeManager = null,
        bool $describeFails = false
    ): BlogDetector {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($factory ?? new CollectionFactory());

        if ($storeManager === null) {
            $store = $this->createStub(Store::class);
            $store->method('getBaseUrl')->willReturn('https://example.com');
            $storeManager = $this->createStub(StoreManagerInterface::class);
            $storeManager->method('getStore')->willReturn($store);
        }

        $connection = $this->createStub(AdapterInterface::class);
        if ($describeFails) {
            $connection->method('describeTable')->willThrowException(new \RuntimeException('no table'));
        } else {
            $connection->method('describeTable')->willReturn($columns);
        }
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        return new BlogDetector(
            $objectManager,
            $storeManager,
            $logger ?? $this->createStub(LoggerInterface::class),
            $resource,
            $supported
        );
    }

    public function testIsBlogInstalledChecksSupportedClasses(): void
    {
        $this->assertFalse($this->detector([])->isBlogInstalled());
        $this->assertFalse($this->detector(['Vendor\\Missing\\Post'])->isBlogInstalled());
        $this->assertTrue($this->detector(['Vendor\\Missing\\Post', Post::class])->isBlogInstalled());
    }

    public function testGetPostByIdRejectsInvalidInput(): void
    {
        $detector = $this->detector(['Magefan\\Blog\\Model\\Post', 'Mageplaza\\Blog\\Model\\Post']);

        $this->assertNull($detector->getPostById('blog_post_view', 0));
        $this->assertNull($detector->getPostById('cms_page_view', 5));
        $this->assertNull($detector->getPostById('BLOG_POST_VIEW', 5));
        $this->assertNull($this->detector([])->getPostById('mpblog_post_view', 5));
    }

    public function testGetBlogPostsResolvesUrlsAndTitles(): void
    {
        $withAbsoluteUrl = new class extends DataObject {
            public function getPostUrl(): string
            {
                return '';
            }

            public function getUrl(): string
            {
                return 'https://example.com/blog/absolute';
            }

            public function getTitle(): string
            {
                return '';
            }

            public function getName(): string
            {
                return ' Named ';
            }
        };
        $withIdentifier = new class (['title' => 'From data']) extends DataObject {
            public function getIdentifier(): string
            {
                return '/slug';
            }
        };
        $withUrlKey = new class extends DataObject {
            public function getUrlKey(): string
            {
                return 'key';
            }

            public function getTitle(): string
            {
                return 'Key title';
            }
        };
        $withPostUrl = new class extends DataObject {
            public function getPostUrl(): string
            {
                return 'https://example.com/p';
            }

            public function getTitle(): string
            {
                return 'Post url';
            }
        };
        $items = [
            new Post(['identifier' => 'hello', 'title' => 'Hello']),
            new Post(['url_key' => 'by-key', 'name' => 'By key']),
            $withAbsoluteUrl,
            $withIdentifier,
            $withUrlKey,
            $withPostUrl,
            new Post(['title' => 'No url']),
            new Post(['identifier' => 'untitled']),
        ];
        $collection = new Collection($items, 'blog_post');

        $posts = $this->detector(
            ['Vendor\\Missing\\Post', Post::class],
            new CollectionFactory($collection),
            ['status' => [], 'enabled' => []]
        )->getBlogPosts(3);

        $this->assertSame([
            ['url' => 'https://example.com/blog/hello', 'title' => 'Hello'],
            ['url' => 'https://example.com/blog/by-key', 'title' => 'By key'],
            ['url' => 'https://example.com/blog/absolute', 'title' => 'Named'],
            ['url' => 'https://example.com/blog/slug', 'title' => 'From data'],
            ['url' => 'https://example.com/blog/key', 'title' => 'Key title'],
            ['url' => 'https://example.com/p', 'title' => 'Post url'],
        ], $posts);
        $this->assertSame(
            [['addStoreFilter', 3], ['addFieldToFilter', 'enabled', 1], ['addActiveFilter']],
            $collection->calls
        );
    }

    public function testActiveColumnFromResourceMainTableOrNone(): void
    {
        $fromResource = new Collection([], '', Collection::resource('post_id', 'mp_post'));
        $this->detector([Post::class], new CollectionFactory($fromResource), ['is_active' => []])->getBlogPosts(1);

        $noColumn = new Collection([], 'blog_post');
        $this->detector([Post::class], new CollectionFactory($noColumn), ['title' => []])->getBlogPosts(1);

        $noTable = new Collection([], '');
        $this->detector([Post::class], new CollectionFactory($noTable), ['is_active' => []])->getBlogPosts(1);

        $broken = new Collection([], 'blog_post');
        $this->detector([Post::class], new CollectionFactory($broken), [], null, null, true)->getBlogPosts(1);

        $this->assertContains(['addFieldToFilter', 'is_active', 1], $fromResource->calls);
        $this->assertSame([['addStoreFilter', 1], ['addActiveFilter']], $noColumn->calls);
        $this->assertSame([['addStoreFilter', 1], ['addActiveFilter']], $noTable->calls);
        $this->assertSame([['addStoreFilter', 1], ['addActiveFilter']], $broken->calls);
    }

    public function testCollectionFailureIsLoggedAndYieldsNoPosts(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Panth SEO BlogDetector: collection load failed', $this->arrayHasKey('class'));

        $posts = $this->detector(
            [Post::class],
            new CollectionFactory(null, new \RuntimeException('db')),
            [],
            $logger
        )->getBlogPosts(1);

        $this->assertSame([], $posts);
    }

    public function testStoreFailureIsLogged(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \RuntimeException('store'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Panth SEO BlogDetector failed to load blog posts', ['error' => 'store']);

        $this->assertSame([], $this->detector([Post::class], null, [], $logger, $storeManager)->getBlogPosts(1));
    }

    public function testModelWithoutCollectionFactoryYieldsNoPosts(): void
    {
        $this->assertSame([], $this->detector([DataObject::class])->getBlogPosts(1));
        $this->assertSame([], $this->detector(['Vendor\\Missing\\Post'])->getBlogPosts(1));
    }

    private function loadPostById(BlogDetector $detector, string $class, int $id): ?object
    {
        $method = new \ReflectionMethod(BlogDetector::class, 'loadPostById');

        return $method->invoke($detector, $class, $id);
    }

    public function testLoadPostByIdUsesResourceIdField(): void
    {
        $match = new Post(['entity_id' => '9', 'title' => 'Nine']);
        $collection = new Collection(
            [new Post(['entity_id' => '8']), $match],
            '',
            Collection::resource('entity_id', 'blog')
        );

        $found = $this->loadPostById($this->detector([], new CollectionFactory($collection)), Post::class, 9);

        $this->assertSame($match, $found);
        $this->assertSame([['addFieldToFilter', 'entity_id', 9], ['setPageSize', 1]], $collection->calls);
    }

    public function testLoadPostByIdDefaultsToPostIdAndReturnsNullWithoutMatch(): void
    {
        $collection = new Collection([new Post(['post_id' => 1])]);

        $this->assertNull($this->loadPostById($this->detector([], new CollectionFactory($collection)), Post::class, 2));
        $this->assertSame([['addFieldToFilter', 'post_id', 2], ['setPageSize', 1]], $collection->calls);
        $this->assertNull($this->loadPostById($this->detector([]), DataObject::class, 2));
    }

    public function testLoadPostByIdLogsFailures(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Panth SEO BlogDetector: current post load failed', $this->arrayHasKey('error'));

        $detector = $this->detector([], new CollectionFactory(null, new \RuntimeException('x')), [], $logger);

        $this->assertNull($this->loadPostById($detector, Post::class, 2));
    }
}
