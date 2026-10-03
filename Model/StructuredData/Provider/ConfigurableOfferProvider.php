<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class ConfigurableOfferProvider extends AbstractProvider
{
    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly Configurable $configurableType,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ?PriceCurrencyInterface $priceCurrency = null
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'configurable_offer';
    }

    public function isApplicable(): bool
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return false;
        }

        if ($product->getTypeId() !== Configurable::TYPE_CODE || $this->config->isProductGroupEnabled()) {
            return false;
        }

        return $this->config->isStructuredDataEnabled('configurable_multi_offer');
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }

        try {
            $store = $this->storeManager->getStore();
            $currency = (string) $store->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = 'USD';
        }

        $children = $this->getVisibleChildren($product);
        if ($children === []) {
            return [];
        }

        $url = (string) $product->getProductUrl();
        $offers = [];
        $prices = [];

        foreach ($children as $child) {
            $offer = $this->buildChildOffer($child, $currency, $url);
            if ($offer !== []) {
                $offers[] = $offer;
                $price = (float) ($offer['price'] ?? 0);
                if ($price > 0) {
                    $prices[] = $price;
                }
            }
        }

        if ($offers === [] || $prices === []) {
            return [];
        }

        $lowPrice = min($prices);
        $highPrice = max($prices);

        return [
            '@type' => 'Product',
            '@id'   => $url . '#product',
            'name'  => (string) $product->getName(),
            'sku'   => (string) $product->getSku(),
            'url'   => $url,
            'offers' => [
                '@type'      => 'AggregateOffer',
                'lowPrice'   => number_format($lowPrice, 2, '.', ''),
                'highPrice'  => number_format($highPrice, 2, '.', ''),
                'offerCount' => count($offers),
                'priceCurrency' => $currency,
                'offers'     => $offers,
            ],
        ];
    }

    private function getVisibleChildren(ProductInterface $product): array
    {
        try {
            $children = $this->configurableType->getUsedProducts($product);
        } catch (\Throwable) {
            return [];
        }

        $visible = [];
        foreach ($children as $child) {
            $status = (int) $child->getStatus();

            if ($status === 1) {
                $visible[] = $child;
            }
        }

        return $visible;
    }

    private function buildChildOffer(ProductInterface $child, string $currency, string $parentUrl): array
    {
        $finalPrice = $child->getFinalPrice();
        if ($finalPrice === null || $finalPrice === false) {
            try {
                $finalPrice = (float) $child->getPriceInfo()->getPrice('final_price')->getValue();
            } catch (\Throwable) {
                $finalPrice = 0.0;
            }
        }
        $finalPrice = $this->convertPrice((float) $finalPrice, $this->priceCurrency);
        if ($finalPrice <= 0.0) {
            return [];
        }

        $availability = $this->getAvailability($child);
        $url = $this->resolveOfferUrl($child, $parentUrl);

        $offer = [
            '@type'         => 'Offer',
            'price'         => number_format($finalPrice, 2, '.', ''),
            'priceCurrency' => $currency,
            'availability'  => $availability,
            'sku'           => (string) $child->getSku(),
        ];
        if ($url !== '') {
            $offer['url'] = $url;
        }
        return $offer;
    }

    private function getAvailability(ProductInterface $product): string
    {
        try {
            $stockItem = $this->stockRegistry->getStockItem(
                (int) $product->getId()
            );
            $isInStock = $stockItem->getIsInStock();
        } catch (\Throwable) {
            $isInStock = false;
        }

        return $isInStock
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }
}
