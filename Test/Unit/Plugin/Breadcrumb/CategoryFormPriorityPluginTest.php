<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Plugin\Breadcrumb;

use Magento\Catalog\Model\Category\DataProvider as CategoryDataProvider;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Plugin\Breadcrumb\CategoryFormPriorityPlugin;
use PHPUnit\Framework\TestCase;

class CategoryFormPriorityPluginTest extends TestCase
{
    private function plugin(bool $enabled): CategoryFormPriorityPlugin
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        return new CategoryFormPriorityPlugin($config);
    }

    public function testDisabledModuleLeavesMetaUntouched(): void
    {
        $meta = ['general' => ['children' => []]];

        $this->assertSame(
            $meta,
            $this->plugin(false)->afterGetMeta($this->createStub(CategoryDataProvider::class), $meta)
        );
    }

    public function testAddsPriorityFieldToSeoFieldset(): void
    {
        $meta = ['search_engine_optimization' => ['children' => ['url_key' => ['x' => 1]]]];

        $result = $this->plugin(true)->afterGetMeta($this->createStub(CategoryDataProvider::class), $meta);

        $children = $result['search_engine_optimization']['children'];
        $this->assertSame(['x' => 1], $children['url_key']);
        $container = $children['container_breadcrumbs_priority'];
        $this->assertSame('container', $container['arguments']['data']['config']['componentType']);
        $this->assertSame(70, $container['arguments']['data']['config']['sortOrder']);
        $field = $container['children']['breadcrumbs_priority']['arguments']['data']['config'];
        $this->assertSame('number', $field['dataType']);
        $this->assertSame('input', $field['formElement']);
        $this->assertSame('breadcrumbs_priority', $field['dataScope']);
        $this->assertSame(['validate-digits' => true], $field['validation']);
        $this->assertSame('Breadcrumb Priority', (string) $field['label']);
        $this->assertStringContainsString('Higher values take precedence.', (string) $field['tooltip']['description']);
    }
}
