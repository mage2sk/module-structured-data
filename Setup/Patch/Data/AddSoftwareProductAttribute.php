<?php
declare(strict_types=1);

namespace Panth\StructuredData\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddSoftwareProductAttribute implements DataPatchInterface
{
    private const GROUP = 'Search Engine Optimization';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $common = [
            'required'                => false,
            'user_defined'            => false,
            'visible'                 => true,
            'visible_on_front'        => false,
            'used_in_product_listing' => false,
            'searchable'              => false,
            'filterable'              => false,
            'comparable'              => false,
            'is_used_in_grid'         => false,
            'is_visible_in_grid'      => false,
            'is_filterable_in_grid'   => false,
            'group'                   => self::GROUP,
        ];

        if (!$eavSetup->getAttributeId(Product::ENTITY, 'panth_is_software')) {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'panth_is_software',
                array_merge($common, [
                    'type'       => 'int',
                    'label'      => 'Is Software',
                    'input'      => 'boolean',
                    'source'     => Boolean::class,
                    'default'    => '0',
                    'global'     => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'sort_order' => 200,
                ])
            );
        }

        if (!$eavSetup->getAttributeId(Product::ENTITY, 'panth_software_category')) {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'panth_software_category',
                array_merge($common, [
                    'type'       => 'varchar',
                    'label'      => 'Software Application Category',
                    'input'      => 'text',
                    'default'    => 'DeveloperApplication',
                    'global'     => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'sort_order' => 210,
                ])
            );
        }

        if (!$eavSetup->getAttributeId(Product::ENTITY, 'panth_software_os')) {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'panth_software_os',
                array_merge($common, [
                    'type'       => 'varchar',
                    'label'      => 'Software Operating System',
                    'input'      => 'text',
                    'default'    => 'Magento 2',
                    'global'     => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'sort_order' => 220,
                ])
            );
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
