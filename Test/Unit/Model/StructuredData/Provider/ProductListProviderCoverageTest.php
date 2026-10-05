<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DB\Select;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\ProductListProvider;
use Psr\Log\LoggerInterface;

class ProductListProviderCoverageTest extends AbstractProviderTestCase
{
    private function provider(array $registry, array $products = [], array $flags = []): ProductListProvider
    {
        $collection = $this->createStub(Collection::class);
        foreach ([
            'addAttributeToSelect', 'addCategoryFilter', 'setVisibility', 'addAttributeToFilter',
            'addStoreFilter', 'addUrlRewrite', 'setPageSize', 'setCurPage',
        ] as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getSelect')->willReturn($this->createStub(Select::class));
        $collection->method('getItems')->willReturn($products);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn('catalog_category_view');

        return new ProductListProvider(
            $this->registry($registry),
            $request,
            $this->storeManager(),
            $this->config([], $flags),
            $factory,
            $this->createStub(Visibility::class),
            $this->createStub(LoggerInterface::class)
        );
    }

    private function category(): Category
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(3);
        $category->method('getName')->willReturn('Mugs');
        $category->method('getUrl')->willReturn('https://example.com/mugs.html');

        return $category;
    }

    public function testCodeAndFlag(): void
    {
        $registry = ['current_category' => $this->category()];

        $this->assertSame('productList', $this->provider($registry)->getCode());
        $this->assertFalse($this->provider($registry)->isApplicable());
        $this->assertTrue(
            $this->provider($registry, [], [Config::XML_SD_PRODUCT_LIST_SCHEMA => true])->isApplicable()
        );
        $this->assertFalse($this->provider([], [], [Config::XML_SD_PRODUCT_LIST_SCHEMA => true])->isApplicable());
    }

    public function testListIsCappedAtTwentyItems(): void
    {
        $products = [];
        for ($i = 1; $i <= 25; $i++) {
            $products[] = $this->product(['getName' => 'Mug ' . $i, 'getProductUrl' => 'https://example.com/mug-' . $i]);
        }

        $node = $this->provider(['current_category' => $this->category()], $products)->getJsonLd();

        $this->assertSame(20, $node['numberOfItems']);
        $this->assertSame(20, end($node['itemListElement'])['position']);
        $this->assertSame('Mug 20', end($node['itemListElement'])['name']);
    }

    public function testProductContextOrNonModelCategoryGivesNoNode(): void
    {
        $product = $this->product(['getName' => 'Mug', 'getProductUrl' => 'https://example.com/mug']);

        $this->assertSame(
            [],
            $this->provider(['current_category' => $this->category(), 'current_product' => $product], [$product])
                ->getJsonLd()
        );
        $this->assertSame(
            [],
            $this->provider(['current_category' => $this->createStub(CategoryInterface::class)], [$product])
                ->getJsonLd()
        );
        $this->assertSame([], $this->provider([], [$product])->getJsonLd());
    }
}
