<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Offer;

use Magento\Catalog\Api\Data\ProductInterface;
use Panth\StructuredData\Helper\Config;

class OfferMerchantFieldsBuilder
{
    private const DIGITAL_TYPES = ['virtual', 'downloadable'];

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function apply(array $offer, ProductInterface $product, string $currency, ?int $storeId = null): array
    {
        try {
            if (!$this->config->isMerchantFieldsEnabled($storeId)) {
                return $offer;
            }

            $isDigital = in_array((string) $product->getTypeId(), self::DIGITAL_TYPES, true);

            if ($this->config->isMerchantReturnEnabled($storeId) && !isset($offer['hasMerchantReturnPolicy'])) {
                $offer['hasMerchantReturnPolicy'] = $this->buildReturnPolicy($isDigital, $storeId);
            }

            if ($this->config->isMerchantShippingEnabled($storeId) && !isset($offer['shippingDetails'])) {
                $offer['shippingDetails'] = $this->buildShippingDetails($isDigital, $currency, $storeId);
            }
        } catch (\Throwable) {
            return $offer;
        }

        return $offer;
    }

    private function buildReturnPolicy(bool $isDigital, ?int $storeId): array
    {
        $country = $this->config->getReturnApplicableCountry($storeId);
        $days = $isDigital ? 0 : $this->config->getReturnPolicyDays($storeId);

        if ($days <= 0) {
            return [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => $country,
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
            ];
        }

        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => $country,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $days,
            'returnMethod' => $this->config->getReturnMethodSchemaUrl($storeId),
            'returnFees' => $this->config->getReturnFeesSchemaUrl($storeId),
        ];
    }

    private function buildShippingDetails(bool $isDigital, string $currency, ?int $storeId): array
    {
        $country = $this->config->getShippingCountry($storeId);

        if ($isDigital) {
            return [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => [
                    '@type' => 'MonetaryAmount',
                    'value' => '0.00',
                    'currency' => $currency,
                ],
                'shippingDestination' => [
                    '@type' => 'DefinedRegion',
                    'addressCountry' => $country,
                ],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => $this->quantitativeDays(0, 0),
                    'transitTime' => $this->quantitativeDays(0, 0),
                ],
            ];
        }

        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => $this->config->getShippingDefaultRate($storeId),
                'currency' => $currency,
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => $country,
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => $this->quantitativeDays(
                    $this->config->getShippingHandlingMin($storeId),
                    $this->config->getShippingHandlingMax($storeId)
                ),
                'transitTime' => $this->quantitativeDays(
                    $this->config->getShippingTransitMin($storeId),
                    $this->config->getShippingTransitMax($storeId)
                ),
            ],
        ];
    }

    private function quantitativeDays(int $min, int $max): array
    {
        return [
            '@type' => 'QuantitativeValue',
            'minValue' => $min,
            'maxValue' => max($min, $max),
            'unitCode' => 'DAY',
        ];
    }
}
