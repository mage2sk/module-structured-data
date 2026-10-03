<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BusinessType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'Organization',   'label' => __('Organization')],
            ['value' => 'LocalBusiness',   'label' => __('Local Business')],
            ['value' => 'Store',           'label' => __('Store')],
            ['value' => 'OnlineStore',     'label' => __('Online Store')],
        ];
    }
}
