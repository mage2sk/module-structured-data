<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductCondition implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'new',          'label' => __('New')],
            ['value' => 'used',         'label' => __('Used')],
            ['value' => 'refurbished',  'label' => __('Refurbished')],
            ['value' => 'damaged',      'label' => __('Damaged')],
        ];
    }
}
