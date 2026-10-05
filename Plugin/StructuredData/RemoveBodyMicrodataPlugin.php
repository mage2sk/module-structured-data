<?php
declare(strict_types=1);

namespace Panth\StructuredData\Plugin\StructuredData;

use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Config\Renderer;
use Panth\StructuredData\Model\StructuredData\ProductPageMicrodataStripper;

class RemoveBodyMicrodataPlugin
{
    private const WRAP_OPEN = '<body ';

    public function __construct(
        private readonly ProductPageMicrodataStripper $stripper
    ) {
    }

    public function afterRenderElementAttributes(Renderer $subject, mixed $result, mixed $elementType = null): mixed
    {
        if ($elementType !== PageConfig::ELEMENT_TYPE_BODY
            || !is_string($result)
            || $result === ''
            || !$this->stripper->isActive()
        ) {
            return $result;
        }

        $tag = $this->stripper->strip(self::WRAP_OPEN . $result . '>');
        if (!str_starts_with($tag, self::WRAP_OPEN) && !str_starts_with($tag, '<body>')) {
            return $result;
        }

        return trim(substr($tag, 5, -1));
    }
}
