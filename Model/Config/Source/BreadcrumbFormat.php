<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BreadcrumbFormat implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'shortest', 'label' => __('Shortest Path')],
            ['value' => 'longest',  'label' => __('Longest Path / Deepest Category')],
        ];
    }
}
