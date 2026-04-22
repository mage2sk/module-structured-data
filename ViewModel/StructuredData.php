<?php
declare(strict_types=1);

namespace Panth\StructuredData\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Composite;

/**
 * Hyva-safe ViewModel exposing the aggregated JSON-LD document to templates.
 */
class StructuredData implements ArgumentInterface
{
    /**
     * @param Composite $composite
     * @param Config $config
     */
    public function __construct(
        private readonly Composite $composite,
        private readonly Config $config
    ) {
    }

    /**
     * Master-switch check.
     */
    public function isEnabled(): bool
    {
        try {
            return $this->config->isEnabled();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Serialised JSON-LD document. Empty string when disabled or no providers emit.
     */
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
