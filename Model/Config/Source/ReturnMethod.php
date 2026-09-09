<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ReturnMethod implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bymail', 'label' => __('Return by mail')],
            ['value' => 'instore', 'label' => __('Return in store')],
            ['value' => 'kiosk', 'label' => __('Return at kiosk')],
        ];
    }
}
