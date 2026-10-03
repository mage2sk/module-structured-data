<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Plugin\Breadcrumb;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Plugin\Breadcrumb\BreadcrumbPlugin;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BreadcrumbPluginTest extends TestCase
{
    private const ORIGINAL = ['home' => ['label' => 'Original']];

    private array $categories = [];

    protected function setUp(): void
    {
        $this->categories = [
            1 => $this->category(1, '1', 0, 'Root Catalog'),
            2 => $this->category(2, '1/2', 1, 'Default'),
            10 => $this->category(10, '1/2/10', 2, 'Men'),
            11 => $this->category(11, '1/2/10/11', 3, 'Tops'),
            20 => $this->category(20, '1/2/20', 2, 'Sale', true, 3),
            30 => $this->category(30, '1/2/30', 2, 'Hidden Parent', false),
            31 => $this->category(31, '1/2/30/31', 3, 'Orphan'),
            40 => $this->category(40, '1/2/40', 2, 'Inactive', false),
            50 => $this->category(50, '1/2/404', 2, 'Broken path'),
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
        $category->method('getUrl')->willReturn('https://example.com/c' . $id . '.html');
        $attribute = null;
        if ($priority !== null) {
            $attribute = $this->createStub(AttributeInterface::class);
            $attribute->method('getValue')->willReturn($priority);
        }
        $category->method('getCustomAttribute')->willReturn($attribute);

        return $category;
    }

    private function plugin(
        mixed $product,
        string $format = '',
        bool $moduleEnabled = true,
        bool $priorityEnabled = true
    ): BreadcrumbPlugin {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn($priorityEnabled);
        $scopeConfig->method('getValue')->willReturn($format);

        $categories = $this->categories;
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(
            static function (int $id) use ($categories) {
                if (!isset($categories[$id])) {
                    throw new NoSuchEntityException(__('missing'));
                }
                return $categories[$id];
            }
        );

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn(string $key) => $key === 'current_product' ? $product : null
        );

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getBaseUrl')->willReturn('https://example.com/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($moduleEnabled);

        return new BreadcrumbPlugin(
            $scopeConfig,
            $repository,
            $registry,
            $storeManager,
            $this->createStub(LoggerInterface::class),
            $config
        );
    }

    private function product(array $categoryIds): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getCategoryIds')->willReturn($categoryIds);
        $product->method('getName')->willReturn('Shirt');

        return $product;
    }

    private function apply(BreadcrumbPlugin $plugin): array
    {
        return $plugin->afterGetBreadcrumbPath($this->createStub(CatalogHelper::class), self::ORIGINAL);
    }

    public function testDisabledOrMissingContextKeepsOriginalPath(): void
    {
        $product = $this->product([10]);

        $this->assertSame(self::ORIGINAL, $this->apply($this->plugin($product, '', false)));
        $this->assertSame(self::ORIGINAL, $this->apply($this->plugin($product, '', true, false)));
        $this->assertSame(self::ORIGINAL, $this->apply($this->plugin(null)));
        $this->assertSame(self::ORIGINAL, $this->apply($this->plugin(new DataObject())));
        $this->assertSame(self::ORIGINAL, $this->apply($this->plugin($this->product([]))));
        $this->assertSame(self::ORIGINAL, $this->apply($this->plugin($this->product([31, 40, 999, 2]))));
    }

    public function testHighestWeightPathWins(): void
    {
        $result = $this->apply($this->plugin($this->product([11, 20])));

        $this->assertSame(['home', 'category20', 'product'], array_keys($result));
        $this->assertSame('Home', (string) $result['home']['label']);
        $this->assertSame('https://example.com/', $result['home']['link']);
        $this->assertTrue($result['home']['first']);
        $this->assertSame('Sale', $result['category20']['label']);
        $this->assertSame('https://example.com/c20.html', $result['category20']['link']);
        $this->assertSame(['label' => 'Shirt', 'title' => null, 'link' => '', 'first' => false, 'last' => true],
            $result['product']);
    }

    public function testTieBreaksFollowFormat(): void
    {
        $longest = $this->apply($this->plugin($this->product([10, 11]), 'longest'));
        $shortest = $this->apply($this->plugin($this->product([11, 10]), 'shortest'));
        $unordered = $this->apply($this->plugin($this->product([11, 10, 50]), 'other'));

        $this->assertSame(['home', 'category10', 'category11', 'product'], array_keys($longest));
        $this->assertSame(['home', 'category10', 'product'], array_keys($shortest));
        $this->assertSame(['home', 'category10', 'category11', 'product'], array_keys($unordered));
    }
}
