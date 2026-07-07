<?php
declare(strict_types=1);

namespace Panth\StructuredData\Plugin\StructuredData;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Store\Model\ScopeInterface;
use Panth\StructuredData\Helper\Config as SeoConfig;

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
        private readonly SeoConfig $seoConfig
    ) {
    }

    public function afterToHtml(AbstractBlock $subject, ?string $result): ?string
    {
        if ($result === null || $result === '') {
            return $result;
        }

        if (!$this->isEnabled()) {
            return $result;
        }

        $blockName = (string) $subject->getNameInLayout();
        if (!in_array($blockName, self::TARGET_BLOCKS, true)) {
            return $result;
        }

        $result = $this->stripNativeJsonLd($result);
        $result = $this->stripMicrodataAttributes($result);

        return $result;
    }

    private function stripNativeJsonLd(string $html): string
    {
        $pattern = '/<script\b[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>.*?<\/script>/is';

        $cleaned = (string) preg_replace_callback($pattern, static function (array $match): string {
            $tag = $match[0];

            if (stripos($tag, 'data-panth-seo') !== false) {
                return $tag;
            }
            return '';
        }, $html);

        return $cleaned !== '' || $html === '' ? $cleaned : $html;
    }

    private function stripMicrodataAttributes(string $html): string
    {
        $html = (string) preg_replace('/\s+itemscope(?=[\s>\/])/i', '', $html);

        $html = (string) preg_replace('/\s+(?:itemprop|itemtype)\s*=\s*(?:"[^"]*"|\'[^\']*\'|\S+)/i', '', $html);

        return $html;
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
