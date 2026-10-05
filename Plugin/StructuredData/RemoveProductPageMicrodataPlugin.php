<?php
declare(strict_types=1);

namespace Panth\StructuredData\Plugin\StructuredData;

use Magento\Framework\View\Layout;
use Panth\StructuredData\Model\StructuredData\ProductPageMicrodataStripper;

class RemoveProductPageMicrodataPlugin
{
    public function __construct(
        private readonly ProductPageMicrodataStripper $stripper
    ) {
    }

    public function afterGetOutput(Layout $subject, mixed $result): mixed
    {
        if (!is_string($result) || $result === '' || !$this->stripper->isActive()) {
            return $result;
        }

        return $this->stripper->strip($result);
    }
}
