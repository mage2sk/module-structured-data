<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class EnergyLabelProvider extends AbstractProvider
{
    private const XML_ENABLED          = 'panth_structured_data/structured_data/energy_label_enabled';
    private const XML_CLASS_ATTRIBUTE  = 'panth_structured_data/structured_data/energy_class_attribute';

    private const GRADE_MAP = [
        'A+++' => 'https://schema.org/EUEnergyEfficiencyCategoryA3Plus',
        'A++'  => 'https://schema.org/EUEnergyEfficiencyCategoryA2Plus',
        'A+'   => 'https://schema.org/EUEnergyEfficiencyCategoryA1Plus',
        'A'    => 'https://schema.org/EUEnergyEfficiencyCategoryA',
        'B'    => 'https://schema.org/EUEnergyEfficiencyCategoryB',
        'C'    => 'https://schema.org/EUEnergyEfficiencyCategoryC',
        'D'    => 'https://schema.org/EUEnergyEfficiencyCategoryD',
        'E'    => 'https://schema.org/EUEnergyEfficiencyCategoryE',
        'F'    => 'https://schema.org/EUEnergyEfficiencyCategoryF',
        'G'    => 'https://schema.org/EUEnergyEfficiencyCategoryG',
    ];

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'energy_label_enabled';
    }

    public function isApplicable(): bool
    {
        if ($this->getCurrentProduct() === null) {
            return false;
        }

        return $this->isFeatureEnabled();
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }

        $energyClass = $this->resolveAttributeValue($product, $this->getEnergyClassAttribute());
        if ($energyClass === '') {
            return [];
        }

        $categoryUrl = $this->gradeToUrl($energyClass);
        if ($categoryUrl === null) {
            return [];
        }

        $details = [
            '@type'                        => 'EnergyConsumptionDetails',
            'hasEnergyEfficiencyCategory'  => $categoryUrl,
        ];

        $scaleMin = $this->resolveAttributeValue($product, 'energy_scale_min');
        $scaleMinUrl = $this->gradeToUrl($scaleMin);
        if ($scaleMinUrl !== null) {
            $details['energyEfficiencyScaleMin'] = $scaleMinUrl;
        }

        $scaleMax = $this->resolveAttributeValue($product, 'energy_scale_max');
        $scaleMaxUrl = $this->gradeToUrl($scaleMax);
        if ($scaleMaxUrl !== null) {
            $details['energyEfficiencyScaleMax'] = $scaleMaxUrl;
        }

        $url = (string) $product->getProductUrl();

        return [
            '@type'                        => 'Product',
            '@id'                          => $url . '#product',
            'hasEnergyConsumptionDetails'  => $details,
        ];
    }

    private function resolveAttributeValue(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        string $attributeCode
    ): string {
        if ($attributeCode === '') {
            return '';
        }

        try {
            if (method_exists($product, 'getAttributeText')) {
                $text = $product->getAttributeText($attributeCode);
                if (is_string($text) && $text !== '') {
                    return trim($text);
                }
            }
        } catch (\Throwable) {
        }

        $raw = $product->getData($attributeCode);

        return is_string($raw) ? trim($raw) : '';
    }

    private function gradeToUrl(string $grade): ?string
    {
        $normalized = strtoupper(trim($grade));

        return self::GRADE_MAP[$normalized] ?? null;
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

    private function getEnergyClassAttribute(): string
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = null;
        }

        $value = (string) ($this->scopeConfig->getValue(
            self::XML_CLASS_ATTRIBUTE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '');

        return $value !== '' ? $value : 'energy_class';
    }
}
