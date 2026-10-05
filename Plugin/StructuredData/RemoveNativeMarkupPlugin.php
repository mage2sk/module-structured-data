<?php
declare(strict_types=1);

namespace Panth\StructuredData\Plugin\StructuredData;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Store\Model\ScopeInterface;
use Panth\StructuredData\Helper\Config as SeoConfig;
use Panth\StructuredData\Model\StructuredData\MicrodataRemover;

class RemoveNativeMarkupPlugin
{
    private const XML_ENABLED = 'panth_structured_data/structured_data/remove_native_markup';

    private const TARGET_BLOCKS = [
        'product.info.main',
        'breadcrumbs',
        'product.price.final',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SeoConfig $seoConfig,
        private readonly MicrodataRemover $microdataRemover
    ) {
    }

    public function afterToHtml(AbstractBlock $subject, ?string $result): ?string
    {
        if ($result === null || $result === '') {
            return $result;
        }

        $blockName = (string) $subject->getNameInLayout();
        if (!in_array($blockName, self::TARGET_BLOCKS, true)) {
            return $result;
        }

        if (!$this->isEnabled()) {
            return $result;
        }

        $result = $this->stripNativeJsonLd($result);
        $result = $this->stripMicrodataAttributes($result);

        return $result;
    }

    private function stripNativeJsonLd(string $html): string
    {
        $pattern = '/<script\b[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>.*?<\/script>/is';

        $cleaned = preg_replace_callback($pattern, static function (array $match): string {
            $tag = $match[0];

            if (stripos($tag, 'data-panth-seo') !== false) {
                return $tag;
            }
            return '';
        }, $html);

        return $cleaned ?? $html;
    }

    private function stripMicrodataAttributes(string $html): string
    {
        return $this->microdataRemover->remove($html);
    }

    private function isEnabled(): bool
    {
        return $this->seoConfig->isEnabled()
            && $this->scopeConfig->isSetFlag(
                self::XML_ENABLED,
                ScopeInterface::SCOPE_STORE
            );
    }
}
