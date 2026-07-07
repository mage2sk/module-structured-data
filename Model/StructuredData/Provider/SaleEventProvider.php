<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class SaleEventProvider extends AbstractProvider
{
    private const XML_ENABLED = 'panth_structured_data/structured_data/sale_event_enabled';

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'sale_event_enabled';
    }

    public function isApplicable(): bool
    {
        if (!$this->isFeatureEnabled()) {
            return false;
        }

        $product = $this->getCurrentProduct();
        if ($product !== null) {
            return $this->hasActiveSpecialPrice($product);
        }

        $category = $this->getCurrentCategory();
        if ($category !== null) {
            return $this->hasCategorySaleEvent($category);
        }

        return false;
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product !== null) {
            return $this->buildProductSaleOffer($product);
        }

        $category = $this->getCurrentCategory();
        if ($category !== null) {
            return $this->buildCategorySaleEvent($category);
        }

        return [];
    }

    private function buildProductSaleOffer(ProductInterface $product): array
    {
        if (!$this->hasActiveSpecialPrice($product)) {
            return [];
        }

        $specialPrice = (float) $product->getData('special_price');
        if ($specialPrice <= 0.0) {
            return [];
        }

        try {
            $store    = $this->storeManager->getStore();
            $currency = (string) $store->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = 'USD';
        }

        $url       = (string) $product->getProductUrl();
        $validFrom = $this->formatDate((string) ($product->getData('special_from_date') ?? ''));
        $validThrough = $this->formatDate((string) ($product->getData('special_to_date') ?? ''));

        $offer = [
            '@type' => 'Offer',
            '@id'   => $url . '#offer-sale',
        ];

        if ($validFrom !== '') {
            $offer['validFrom'] = $validFrom;
        }

        if ($validThrough !== '') {
            $offer['validThrough'] = $validThrough;
        }

        $priceSpec = [
            '@type'         => 'UnitPriceSpecification',
            'price'         => number_format($specialPrice, 2, '.', ''),
            'priceCurrency' => $currency,
        ];

        if ($validFrom !== '') {
            $priceSpec['validFrom'] = $validFrom;
        }

        if ($validThrough !== '') {
            $priceSpec['validThrough'] = $validThrough;
        }

        $offer['priceSpecification'] = $priceSpec;

        return $offer;
    }

    private function buildCategorySaleEvent(\Magento\Catalog\Api\Data\CategoryInterface $category): array
    {
        $eventName = trim((string) ($category->getData('sale_event_name') ?? ''));
        $fromDate  = $this->formatDate((string) ($category->getData('sale_from_date') ?? ''));
        $toDate    = $this->formatDate((string) ($category->getData('sale_to_date') ?? ''));

        if ($eventName !== '' && $fromDate !== '' && $toDate !== '') {
            return $this->buildSaleEventNode($eventName, $fromDate, $toDate, $category);
        }

        $range = $this->deriveSaleDateRange($category);
        if ($range === null) {
            return [];
        }

        $fallbackName = $eventName !== ''
            ? $eventName
            : (string) $category->getName() . ' Sale';

        return $this->buildSaleEventNode(
            $fallbackName,
            $range['from'],
            $range['through'],
            $category
        );
    }

    private function buildSaleEventNode(
        string $name,
        string $startDate,
        string $endDate,
        \Magento\Catalog\Api\Data\CategoryInterface $category
    ): array {
        try {
            $categoryUrl = (string) $category->getUrl();
        } catch (\Throwable) {
            $categoryUrl = '';
        }

        $node = [
            '@type'     => 'SaleEvent',
            'name'      => $name,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ];

        if ($categoryUrl !== '') {
            $node['url'] = $categoryUrl;
        }

        $description = trim((string) ($category->getData('sale_event_description') ?? ''));
        if ($description !== '') {
            $node['description'] = $description;
        }

        return $node;
    }

    private function hasActiveSpecialPrice(ProductInterface $product): bool
    {
        $specialPrice = $product->getData('special_price');
        if ($specialPrice === null || $specialPrice === '' || (float) $specialPrice <= 0.0) {
            return false;
        }

        $now = $this->timezone->date()->format('Y-m-d H:i:s');

        $fromDate = (string) ($product->getData('special_from_date') ?? '');
        if ($fromDate !== '') {
            try {
                $fromTs = strtotime($fromDate);
                if ($fromTs !== false && $now < date('Y-m-d H:i:s', $fromTs)) {
                    return false;
                }
            } catch (\Throwable) {
            }
        }

        $toDate = (string) ($product->getData('special_to_date') ?? '');
        if ($toDate !== '') {
            try {
                $toTs = strtotime($toDate);
                if ($toTs !== false && $now > date('Y-m-d', $toTs) . ' 23:59:59') {
                    return false;
                }
            } catch (\Throwable) {
            }
        }

        return true;
    }

    private function hasCategorySaleEvent(\Magento\Catalog\Api\Data\CategoryInterface $category): bool
    {
        $eventName = trim((string) ($category->getData('sale_event_name') ?? ''));
        $fromDate  = (string) ($category->getData('sale_from_date') ?? '');
        $toDate    = (string) ($category->getData('sale_to_date') ?? '');

        if ($eventName !== '' && $fromDate !== '' && $toDate !== '') {
            $now = $this->timezone->date()->format('Y-m-d');
            $formattedFrom = $this->formatDate($fromDate);
            $formattedTo   = $this->formatDate($toDate);
            if ($formattedFrom !== '' && $formattedTo !== '' && $now >= $formattedFrom && $now <= $formattedTo) {
                return true;
            }
        }

        return $this->deriveSaleDateRange($category) !== null;
    }

    private function deriveSaleDateRange(\Magento\Catalog\Api\Data\CategoryInterface $category): ?array
    {
        try {
            $collection = $category->getProductCollection();
            $collection->addAttributeToSelect(['special_price', 'special_from_date', 'special_to_date']);
            $collection->addFieldToFilter('special_price', ['gt' => 0]);
            $collection->setPageSize(50);

            $collection->getSelect()->distinct(true);
            $products = $collection->getItems();
        } catch (\Throwable) {
            return null;
        }

        $now       = $this->timezone->date()->format('Y-m-d H:i:s');
        $earliest  = null;
        $latest    = null;
        $hasActive = false;

        foreach ($products as $product) {
            if (!$this->hasActiveSpecialPrice($product)) {
                continue;
            }

            $hasActive = true;

            $from = $this->formatDate((string) ($product->getData('special_from_date') ?? ''));
            $to   = $this->formatDate((string) ($product->getData('special_to_date') ?? ''));

            if ($from !== '' && ($earliest === null || $from < $earliest)) {
                $earliest = $from;
            }

            if ($to !== '' && ($latest === null || $to > $latest)) {
                $latest = $to;
            }
        }

        if (!$hasActive) {
            return null;
        }

        return [
            'from'    => $earliest ?? $this->timezone->date()->format('Y-m-d'),
            'through' => $latest ?? $this->timezone->date()->modify('+30 days')->format('Y-m-d'),
        ];
    }

    private function formatDate(string $dateString): string
    {
        $dateString = trim($dateString);
        if ($dateString === '') {
            return '';
        }

        try {
            $ts = strtotime($dateString);
            if ($ts === false) {
                return '';
            }

            return date('Y-m-d', $ts);
        } catch (\Throwable) {
            return '';
        }
    }

    private function isFeatureEnabled(): bool
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = null;
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
