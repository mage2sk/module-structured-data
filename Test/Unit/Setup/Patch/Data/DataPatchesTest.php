<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Panth\StructuredData\Setup\Patch\Data\AddBreadcrumbPriorityAttribute;
use Panth\StructuredData\Setup\Patch\Data\AddSoftwareProductAttribute;
use Panth\StructuredData\Setup\Patch\Data\MigrateConfigPaths;
use PHPUnit\Framework\TestCase;

class DataPatchesTest extends TestCase
{
    private function setupMock(): ModuleDataSetupInterface
    {
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->expects($this->once())->method('startSetup');
        $setup->expects($this->once())->method('endSetup');

        return $setup;
    }

    private function factory(EavSetup $eavSetup): EavSetupFactory
    {
        $factory = $this->createStub(EavSetupFactory::class);
        $factory->method('create')->willReturn($eavSetup);

        return $factory;
    }

    public function testBreadcrumbPriorityAttributeIsCreatedAndAssignedToEverySet(): void
    {
        $eavSetup = $this->createMock(EavSetup::class);
        $eavSetup->method('getAttributeId')->willReturn(false);
        $eavSetup->expects($this->once())
            ->method('addAttribute')
            ->with(
                'catalog_category',
                'breadcrumbs_priority',
                $this->callback(static fn(array $def) => $def['type'] === 'int'
                    && $def['group'] === 'Search Engine Optimization'
                    && $def['sort_order'] === 70)
            );
        $eavSetup->method('getEntityTypeId')->willReturn(3);
        $eavSetup->method('getAllAttributeSetIds')->willReturn([3, 7]);
        $eavSetup->method('getAttributeGroupId')->willReturnCallback(
            static function ($type, $setId) {
                if ($setId === 7) {
                    throw new \Exception('no seo group');
                }
                return 11;
            }
        );
        $eavSetup->method('getDefaultAttributeGroupId')->willReturn(99);
        $assigned = [];
        $eavSetup->method('addAttributeToSet')->willReturnCallback(
            static function (...$args) use (&$assigned, $eavSetup) {
                $assigned[] = array_slice($args, 0, 4);
                return $eavSetup;
            }
        );

        $patch = new AddBreadcrumbPriorityAttribute($this->setupMock(), $this->factory($eavSetup));

        $this->assertSame($patch, $patch->apply());
        $this->assertSame(
            [[3, 3, 11, 'breadcrumbs_priority'], [3, 7, 99, 'breadcrumbs_priority']],
            $assigned
        );
        $this->assertSame([], AddBreadcrumbPriorityAttribute::getDependencies());
        $this->assertSame([], $patch->getAliases());
    }

    public function testExistingBreadcrumbAttributeIsNotRecreated(): void
    {
        $eavSetup = $this->createMock(EavSetup::class);
        $eavSetup->method('getAttributeId')->willReturn(42);
        $eavSetup->expects($this->never())->method('addAttribute');
        $eavSetup->method('getEntityTypeId')->willReturn(3);
        $eavSetup->method('getAllAttributeSetIds')->willReturn([]);
        $eavSetup->expects($this->never())->method('addAttributeToSet');

        (new AddBreadcrumbPriorityAttribute($this->setupMock(), $this->factory($eavSetup)))->apply();
    }

    public function testSoftwareAttributesAreAddedOnlyWhenMissing(): void
    {
        $eavSetup = $this->createMock(EavSetup::class);
        $eavSetup->method('getAttributeId')->willReturnCallback(
            static fn($entity, string $code) => $code === 'panth_software_category' ? 5 : false
        );
        $added = [];
        $eavSetup->expects($this->exactly(2))
            ->method('addAttribute')
            ->willReturnCallback(static function ($entity, $code, array $def) use (&$added, $eavSetup) {
                $added[$code] = [$entity, $def];
                return $eavSetup;
            });

        $patch = new AddSoftwareProductAttribute($this->setupMock(), $this->factory($eavSetup));

        $this->assertSame($patch, $patch->apply());
        $this->assertSame(['panth_is_software', 'panth_software_os'], array_keys($added));
        $this->assertSame('catalog_product', $added['panth_is_software'][0]);
        $this->assertSame('boolean', $added['panth_is_software'][1]['input']);
        $this->assertSame('Search Engine Optimization', $added['panth_is_software'][1]['group']);
        $this->assertSame('Magento 2', $added['panth_software_os'][1]['default']);
        $this->assertFalse($added['panth_software_os'][1]['searchable']);
        $this->assertSame([], AddSoftwareProductAttribute::getDependencies());
        $this->assertSame([], $patch->getAliases());
    }

    public function testConfigPathsAreRewrittenForEachLegacySection(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quote')->willReturnCallback(static fn($v) => "'" . $v . "'");
        $connection->method('quoteInto')->willReturnCallback(
            static fn(string $text, $value) => str_replace('?', "'" . $value . "'", $text)
        );
        $updates = [];
        $connection->expects($this->exactly(4))
            ->method('update')
            ->willReturnCallback(static function ($table, array $bind, $where) use (&$updates) {
                $updates[] = [$table, (string) $bind['path'], $where];
                return 1;
            });

        $setup = $this->createStub(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturn('pfx_core_config_data');

        $patch = new MigrateConfigPaths($setup);

        $this->assertSame($patch, $patch->apply());
        $this->assertSame([
            'pfx_core_config_data',
            "REPLACE(path, 'panth_seo/structured_data/', 'panth_structured_data/structured_data/')",
            "path LIKE 'panth_seo/structured_data/%'",
        ], $updates[0]);
        $this->assertSame("path LIKE 'panth_seo/breadcrumbs/%'", $updates[3][2]);
        $this->assertSame([], MigrateConfigPaths::getDependencies());
        $this->assertSame([], $patch->getAliases());
    }
}
