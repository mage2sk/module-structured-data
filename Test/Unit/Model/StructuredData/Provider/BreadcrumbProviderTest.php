<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\BreadcrumbProvider;

class BreadcrumbProviderTest extends AbstractProviderTestCase
{
    private array $categories = [];

    protected function setUp(): void
    {
        $this->categories = [
            2 => $this->category(2, '1/2', 1, 'Root'),
            10 => $this->category(10, '1/2/10', 2, 'Clothing', true, 5),
            11 => $this->category(11, '1/2/10/11', 3, 'Shirts'),
            12 => $this->category(12, '1/2/12', 2, 'Hidden', false),
            13 => $this->category(13, '1/99/13', 4, 'Other Root'),
            14 => $this->category(14, '1/2/14', 2, 'Sale', true, 5),
        ];
    }

    private function category(
        int $id,
        string $path,
        int $level,
        string $name,
        bool $active = true,
        ?int $priority = null
    ): Category {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getPath')->willReturn($path);
        $category->method('getLevel')->willReturn($level);
        $category->method('getIsActive')->willReturn($active);
        $category->method('getName')->willReturn($name);
        $category->method('getUrl')->willReturn('https://example.com/' . strtolower(str_replace(' ', '-', $name)) . '.html');
        $attribute = null;
        if ($priority !== null) {
            $attribute = $this->createStub(AttributeInterface::class);
            $attribute->method('getValue')->willReturn((string) $priority);
        }
        $category->method('getCustomAttribute')->willReturn($attribute);

        return $category;
    }

    private function repository(): CategoryRepositoryInterface
    {
        $categories = $this->categories;
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(
            static function (int $id) use ($categories) {
                if ($id === 666) {
                    throw new \RuntimeException('broken');
                }
                if (!isset($categories[$id])) {
                    throw new NoSuchEntityException(__('missing'));
                }
                return $categories[$id];
            }
        );

        return $repository;
    }

    private function httpRequest(string $action = 'catalog_product_view', string $path = '/shirt.html'): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getPathInfo')->willReturn($path);
        $request->method('getFullActionName')->willReturn($action);

        return $request;
    }

    private function provider(
        array $registry,
        array $values = [],
        array $flags = [],
        ?Http $request = null,
        ?StoreManagerInterface $storeManager = null
    ): BreadcrumbProvider {
        return new BreadcrumbProvider(
            $this->registry($registry),
            $request ?? $this->httpRequest(),
            $storeManager ?? $this->storeManager(),
            $this->config(),
            $this->repository(),
            $this->scopeConfig($values, $flags)
        );
    }

    private function shirt(array $categoryIds): \Magento\Catalog\Model\Product
    {
        return $this->product([
            'getCategoryIds' => $categoryIds,
            'getName' => 'Blue Shirt',
            'getProductUrl' => 'https://example.com/shirt.html',
        ]);
    }

    private function names(array $node): array
    {
        return array_column($node['itemListElement'], 'name');
    }

    public function testCodeAndEmptyContext(): void
    {
        $provider = $this->provider([]);

        $this->assertSame('breadcrumb', $provider->getCode());
        $this->assertSame([], $provider->getJsonLd());
    }

    public function testProductPicksDeepestActiveCategoryInStoreRoot(): void
    {
        $node = $this->provider(['current_product' => $this->shirt([10, 11, 12, 13, 99, 666])])->getJsonLd();

        $this->assertSame('BreadcrumbList', $node['@type']);
        $this->assertSame('https://example.com/#breadcrumb-' . sha1('/shirt.html'), $node['@id']);
        $this->assertSame(['Home', 'Clothing', 'Shirts', 'Blue Shirt'], $this->names($node));
        $this->assertSame([1, 2, 3, 4], array_column($node['itemListElement'], 'position'));
        $this->assertSame('https://example.com/', $node['itemListElement'][0]['item']);
        $this->assertSame('https://example.com/shirts.html', $node['itemListElement'][2]['item']);
        $this->assertSame('https://example.com/shirt.html', $node['itemListElement'][3]['item']);
    }

    public function testPriorityWeightWinsOverDepth(): void
    {
        $node = $this->provider(
            ['current_product' => $this->shirt([11, 14])],
            [],
            [Config::XML_BREADCRUMBS_PRIORITY_ENABLED => true]
        )->getJsonLd();

        $this->assertSame(['Home', 'Sale', 'Blue Shirt'], $this->names($node));
    }

    public function testPriorityTieUsesShortestOrLongestFormat(): void
    {
        $this->categories[11] = $this->category(11, '1/2/10/11', 3, 'Shirts', true, 5);
        $flags = [Config::XML_BREADCRUMBS_PRIORITY_ENABLED => true];

        $shortest = $this->provider(
            ['current_product' => $this->shirt([11, 14])],
            [Config::XML_BREADCRUMBS_FORMAT => 'shortest'],
            $flags
        )->getJsonLd();
        $longest = $this->provider(
            ['current_product' => $this->shirt([14, 11])],
            [Config::XML_BREADCRUMBS_FORMAT => 'longest'],
            $flags
        )->getJsonLd();

        $this->assertSame(['Home', 'Sale', 'Blue Shirt'], $this->names($shortest));
        $this->assertSame(['Home', 'Clothing', 'Shirts', 'Blue Shirt'], $this->names($longest));
    }

    public function testProductWithoutUsableCategoriesGetsHomeAndProduct(): void
    {
        $withNone = $this->provider(['current_product' => $this->shirt([])])->getJsonLd();
        $withInactive = $this->provider(['current_product' => $this->shirt([12, 13])])->getJsonLd();

        $this->assertSame(['Home', 'Blue Shirt'], $this->names($withNone));
        $this->assertSame(['Home', 'Blue Shirt'], $this->names($withInactive));
    }

    public function testStoreFailureDisablesRootFilterAndUsesRelativeBase(): void
    {
        $node = $this->provider(
            ['current_product' => $this->shirt([13])],
            [],
            [],
            null,
            $this->failingStoreManager()
        )->getJsonLd();

        $this->assertSame('/', $node['itemListElement'][0]['item']);
        $this->assertSame(['Home', 'Other Root', 'Blue Shirt'], $this->names($node));
    }

    public function testCategoryPageSkipsMissingAndBrokenPathNodes(): void
    {
        $category = $this->category(50, '1/2/10/404/666/12/11', 3, 'Shirts');

        $node = $this->provider(['current_category' => $category])->getJsonLd();

        $this->assertSame(['Home', 'Clothing', 'Shirts'], $this->names($node));
    }

    public function testCmsPageAddsItsCrumbExceptOnHomePage(): void
    {
        $page = $this->createStub(PageInterface::class);
        $page->method('getTitle')->willReturn('About Us');
        $page->method('getIdentifier')->willReturn('/about-us');

        $node = $this->provider(['cms_page' => $page], [], [], $this->httpRequest('cms_page_view', '/about-us'))
            ->getJsonLd();
        $home = $this->provider(['cms_page' => $page], [], [], $this->httpRequest('cms_index_index', '/'))
            ->getJsonLd();

        $this->assertSame(['Home', 'About Us'], $this->names($node));
        $this->assertSame('https://example.com/about-us', $node['itemListElement'][1]['item']);
        $this->assertSame([], $home);
    }

    public function testLegacyCmsRegistryKeyIsHonoured(): void
    {
        $page = $this->createStub(PageInterface::class);
        $page->method('getTitle')->willReturn('Legal');
        $page->method('getIdentifier')->willReturn('legal');

        $node = $this->provider(['current_cms_page' => $page], [], [], $this->httpRequest('cms_page_view'))
            ->getJsonLd();

        $this->assertSame(['Home', 'Legal'], $this->names($node));
    }
}
