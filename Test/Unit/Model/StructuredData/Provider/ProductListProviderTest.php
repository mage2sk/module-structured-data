<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\ProductListProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProductListProviderTest extends TestCase
{
    private Category $categoryMock;

    protected function setUp(): void
    {
        $this->categoryMock = $this->createStub(Category::class);
        $this->categoryMock->method('getId')->willReturn(7);
        $this->categoryMock->method('getName')->willReturn('Water Bottles');
        $this->categoryMock->method('getUrl')->willReturn('https://example.com/water-bottles.html');
    }

    public function testBuildsItemListFromCategoryProductCollection(): void
    {
        $collection = $this->createMock(Collection::class);
        $this->configureCollection($collection);
        $collection->expects($this->once())
            ->method('addCategoryFilter')
            ->with($this->categoryMock)
            ->willReturnSelf();
        $collection->expects($this->once())
            ->method('setVisibility')
            ->with([2, 4])
            ->willReturnSelf();
        $collection->expects($this->once())
            ->method('addUrlRewrite')
            ->with(7)
            ->willReturnSelf();
        $collection->expects($this->once())
            ->method('setPageSize')
            ->with(20)
            ->willReturnSelf();
        $collection->method('getItems')->willReturn([
            $this->product('Stainless Steel Water Bottle', 'https://example.com/stainless-steel-water-bottle.html'),
            $this->product('Glass Water Bottle', 'https://example.com/glass-water-bottle.html'),
        ]);

        $node = $this->provider($collection)->getJsonLd();

        $this->assertSame('ItemList', $node['@type']);
        $this->assertSame('https://example.com/water-bottles.html#item-list', $node['@id']);
        $this->assertSame('Water Bottles', $node['name']);
        $this->assertSame('https://example.com/water-bottles.html', $node['url']);
        $this->assertSame(2, $node['numberOfItems']);
        $this->assertSame([
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'url'      => 'https://example.com/stainless-steel-water-bottle.html',
                'name'     => 'Stainless Steel Water Bottle',
            ],
            [
                '@type'    => 'ListItem',
                'position' => 2,
                'url'      => 'https://example.com/glass-water-bottle.html',
                'name'     => 'Glass Water Bottle',
            ],
        ], $node['itemListElement']);
    }

    public function testSkipsProductsWithoutNameOrUrlAndKeepsPositionsContiguous(): void
    {
        $collection = $this->createStub(Collection::class);
        $this->configureCollection($collection);
        $collection->method('getItems')->willReturn([
            $this->product('', 'https://example.com/nameless.html'),
            $this->product('Insulated Tumbler', 'https://example.com/insulated-tumbler.html'),
            $this->product('No Url Product', ''),
            $this->product('Travel Mug', 'https://example.com/travel-mug.html'),
        ]);

        $node = $this->provider($collection)->getJsonLd();

        $this->assertSame(2, $node['numberOfItems']);
        $this->assertSame([1, 2], array_column($node['itemListElement'], 'position'));
        $this->assertSame(['Insulated Tumbler', 'Travel Mug'], array_column($node['itemListElement'], 'name'));
    }

    public function testReturnsEmptyNodeAndLogsWarningWhenCollectionFails(): void
    {
        $collection = $this->createStub(Collection::class);
        $this->configureCollection($collection);
        $collection->method('getItems')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('provider "productList"'),
                    $this->stringContains('category 7'),
                    $this->stringContains('db down')
                ),
                $this->arrayHasKey('exception')
            );

        $this->assertSame([], $this->provider($collection, $logger)->getJsonLd());
    }

    public function testReturnsEmptyNodeWhenCategoryHasNoProducts(): void
    {
        $collection = $this->createStub(Collection::class);
        $this->configureCollection($collection);
        $collection->method('getItems')->willReturn([]);

        $this->assertSame([], $this->provider($collection)->getJsonLd());
    }

    private function configureCollection(Stub $collection): void
    {
        foreach ([
            'addAttributeToSelect',
            'addCategoryFilter',
            'setVisibility',
            'addAttributeToFilter',
            'addStoreFilter',
            'addUrlRewrite',
            'setPageSize',
            'setCurPage',
        ] as $fluentMethod) {
            $collection->method($fluentMethod)->willReturnSelf();
        }
        $collection->method('getSelect')->willReturn($this->createStub(Select::class));
    }

    public function testIsApplicableOnCategoryPage(): void
    {
        $provider = $this->provider($this->createStub(Collection::class), null, $this->request('catalog_category_view'));

        $this->assertTrue($provider->isApplicable());
    }

    public function testNotApplicableOnProductPageReachedFromCategory(): void
    {
        $product = $this->createStub(Product::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->never())->method('getItems');

        $provider = $this->provider($collection, null, $this->request('catalog_product_view'), $product);

        $this->assertFalse($provider->isApplicable());
        $this->assertSame([], $provider->getJsonLd());
    }

    public function testNotApplicableOnOtherPagesWithRegisteredCategory(): void
    {
        foreach (['catalogsearch_result_index', 'cms_page_view', ''] as $action) {
            $provider = $this->provider($this->createStub(Collection::class), null, $this->request($action));
            $this->assertFalse($provider->isApplicable(), $action);
        }
    }

    private function request(string $action): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn($action);

        return $request;
    }

    private function provider(
        Collection $collection,
        ?LoggerInterface $logger = null,
        ?RequestInterface $request = null,
        ?Product $currentProduct = null
    ): ProductListProvider {
        $registryMock = $this->createStub(Registry::class);
        $registryMock->method('registry')->willReturnMap([
            ['current_category', $this->categoryMock],
            ['current_product', $currentProduct],
        ]);

        $configMock = $this->createStub(Config::class);
        $configMock->method('isProductListSchemaEnabled')->willReturn(true);

        $collectionFactoryMock = $this->createStub(CollectionFactory::class);
        $collectionFactoryMock->method('create')->willReturn($collection);

        $visibilityMock = $this->createStub(Visibility::class);
        $visibilityMock->method('getVisibleInCatalogIds')->willReturn([2, 4]);

        return new ProductListProvider(
            $registryMock,
            $request ?? $this->createStub(RequestInterface::class),
            $this->createStub(StoreManagerInterface::class),
            $configMock,
            $collectionFactoryMock,
            $visibilityMock,
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    private function product(string $name, string $url): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getName')->willReturn($name);
        $product->method('getProductUrl')->willReturn($url);

        return $product;
    }
}
