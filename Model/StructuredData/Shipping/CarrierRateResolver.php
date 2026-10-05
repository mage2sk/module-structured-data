<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Shipping;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory as TablerateCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CarrierRateResolver
{
    public const XML_CARRIER_RATES_ENABLED = 'panth_structured_data/structured_data/multiRegionShipping';

    private const XML_TABLERATE_ACTIVE    = 'carriers/tablerate/active';
    private const XML_TABLERATE_CONDITION = 'carriers/tablerate/condition_name';
    private const XML_TABLERATE_TITLE     = 'carriers/tablerate/title';
    private const XML_FLATRATE_ACTIVE     = 'carriers/flatrate/active';
    private const XML_FLATRATE_PRICE      = 'carriers/flatrate/price';
    private const XML_FLATRATE_TITLE      = 'carriers/flatrate/title';
    private const XML_FLATRATE_NAME       = 'carriers/flatrate/name';

    private const VALUE_CONDITIONS = ['package_value', 'package_value_with_discount'];
    private const MAX_TABLE_ROWS   = 500;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ?TablerateCollectionFactory $tablerateCollectionFactory = null,
        private readonly ?PriceCurrencyInterface $priceCurrency = null
    ) {
    }

    public function resolve(ProductInterface $product, ?int $storeId = null): array
    {
        try {
            if (!$this->flag(self::XML_CARRIER_RATES_ENABLED, $storeId)) {
                return [];
            }

            $entries = $this->getTableRateEntries($product, $storeId);
            if ($entries !== []) {
                return $entries;
            }

            return $this->getFlatRateEntries($storeId);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getTableRateEntries(ProductInterface $product, ?int $storeId): array
    {
        if ($this->tablerateCollectionFactory === null || !$this->flag(self::XML_TABLERATE_ACTIVE, $storeId)) {
            return [];
        }

        $condition = (string) ($this->value(self::XML_TABLERATE_CONDITION, $storeId) ?? '');
        if ($condition === '') {
            return [];
        }

        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();
        $productValue = $this->resolveConditionValue($product, $condition);

        $collection = $this->tablerateCollectionFactory->create();
        $collection->addFieldToFilter('website_id', $websiteId);
        $collection->addFieldToFilter('condition_name', $condition);
        $collection->setPageSize(self::MAX_TABLE_ROWS);

        $best = [];
        foreach ($collection as $rate) {
            if ((int) $rate->getData('dest_region_id') !== 0) {
                continue;
            }
            $zip = trim((string) ($rate->getData('dest_zip') ?? '*'));
            if ($zip !== '*' && $zip !== '') {
                continue;
            }
            $threshold = (float) $rate->getData('condition_value');
            if ($threshold > $productValue) {
                continue;
            }
            $country = strtoupper(trim((string) ($rate->getData('dest_country_id') ?? '')));
            if ($country === '0' || $country === '*') {
                $country = '';
            }
            if (isset($best[$country]) && $best[$country]['threshold'] > $threshold) {
                continue;
            }
            $best[$country] = [
                'threshold' => $threshold,
                'price'     => max(0.0, (float) $rate->getData('price')),
            ];
        }

        if (count($best) > 1) {
            unset($best['']);
        }

        $label = trim((string) ($this->value(self::XML_TABLERATE_TITLE, $storeId) ?? ''));
        $entries = [];
        foreach ($best as $country => $row) {
            $entries[] = [
                'label'   => $label,
                'country' => (string) $country,
                'cost'    => $this->convert($row['price']),
            ];
        }

        return $entries;
    }

    private function getFlatRateEntries(?int $storeId): array
    {
        if (!$this->flag(self::XML_FLATRATE_ACTIVE, $storeId)) {
            return [];
        }

        $price = max(0.0, (float) ($this->value(self::XML_FLATRATE_PRICE, $storeId) ?? 0.0));
        $title = trim((string) ($this->value(self::XML_FLATRATE_TITLE, $storeId) ?? ''));
        $name  = trim((string) ($this->value(self::XML_FLATRATE_NAME, $storeId) ?? ''));
        $label = trim($title . ($title !== '' && $name !== '' ? ' - ' : '') . $name);

        return [
            [
                'label'   => $label,
                'country' => '',
                'cost'    => $this->convert($price),
            ],
        ];
    }

    private function resolveConditionValue(ProductInterface $product, string $condition): float
    {
        if (in_array($condition, self::VALUE_CONDITIONS, true)) {
            $price = $product->getFinalPrice();
            if ($price === null || $price === false) {
                $price = $product->getPrice();
            }
            return max(0.0, (float) $price);
        }

        if ($condition === 'package_weight') {
            return max(0.0, (float) $product->getWeight());
        }

        return 1.0;
    }

    private function convert(float $amount): float
    {
        if ($this->priceCurrency === null) {
            return $amount;
        }
        try {
            $converted = $this->priceCurrency->convert($amount);
        } catch (\Throwable) {
            return $amount;
        }

        return is_numeric($converted) ? (float) $converted : $amount;
    }

    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function value(string $path, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
