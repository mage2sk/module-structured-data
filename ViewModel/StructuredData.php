<?php
declare(strict_types=1);

namespace Panth\StructuredData\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Composite;

class StructuredData implements ArgumentInterface
{
    public function __construct(
        private readonly Composite $composite,
        private readonly Config $config
    ) {
    }

    public function isEnabled(): bool
    {
        try {
            return $this->config->isEnabled();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getJson(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }
        try {
            return $this->composite->build();
        } catch (\Throwable) {
            return '';
        }
    }
}
