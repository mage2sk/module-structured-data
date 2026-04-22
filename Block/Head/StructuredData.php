<?php
declare(strict_types=1);

namespace Panth\StructuredData\Block\Head;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\StructuredData\ViewModel\StructuredData as StructuredDataViewModel;

/**
 * Head block that emits the aggregated JSON-LD document produced by the
 * StructuredData ViewModel.
 */
class StructuredData extends Template
{
    /**
     * @param Context $context
     * @param StructuredDataViewModel $viewModel
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly StructuredDataViewModel $viewModel,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * JSON payload for the <script type="application/ld+json"> tag.
     */
    public function getJson(): string
    {
        return $this->viewModel->getJson();
    }

    /**
     * Whether the structured data module is enabled in config.
     */
    public function isEnabled(): bool
    {
        return $this->viewModel->isEnabled();
    }
}
