<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\StructuredData\Helper\Config as SeoConfig;

class ProductPageMicrodataStripper
{
    private const XML_REMOVE_NATIVE = 'panth_structured_data/structured_data/remove_native_markup';

    private const PRODUCT_ACTION = 'catalog_product_view';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SeoConfig $seoConfig,
        private readonly RequestInterface $request,
        private readonly MicrodataRemover $remover
    ) {
    }

    public function isActive(): bool
    {
        if (!$this->request instanceof HttpRequest
            || strtolower((string) $this->request->getFullActionName()) !== self::PRODUCT_ACTION
        ) {
            return false;
        }

        return $this->seoConfig->isEnabled()
            && $this->seoConfig->isStructuredDataEnabled('product')
            && $this->scopeConfig->isSetFlag(self::XML_REMOVE_NATIVE, ScopeInterface::SCOPE_STORE);
    }

    public function strip(string $html): string
    {
        return $this->remover->remove($html);
    }
}
