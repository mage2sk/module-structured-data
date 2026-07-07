<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ReturnPolicyType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'refund',   'label' => __('Refund')],
            ['value' => 'exchange', 'label' => __('Exchange')],
        ];
    }
}
