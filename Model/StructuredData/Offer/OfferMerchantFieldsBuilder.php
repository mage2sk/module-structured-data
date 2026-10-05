<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Offer;

use Magento\Catalog\Api\Data\ProductInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Shipping\CarrierRateResolver;

class OfferMerchantFieldsBuilder
{
    private const DIGITAL_TYPES = ['virtual', 'downloadable'];

    public function __construct(
        private readonly Config $config,
        private readonly ?CarrierRateResolver $carrierRateResolver = null
    ) {
    }

    public function apply(array $offer, ProductInterface $product, string $currency, ?int $storeId = null): array
    {
        try {
            if (!$this->config->isMerchantFieldsEnabled($storeId)
                || $this->config->isSoftwareProduct($product, $storeId)
            ) {
                return $offer;
            }

            $isDigital = in_array((string) $product->getTypeId(), self::DIGITAL_TYPES, true);

            if ($this->config->isMerchantReturnEnabled($storeId) && !isset($offer['hasMerchantReturnPolicy'])) {
                $offer['hasMerchantReturnPolicy'] = $this->buildReturnPolicy($isDigital, $storeId);
            }

            if ($this->config->isMerchantShippingEnabled($storeId) && !isset($offer['shippingDetails'])) {
                $offer['shippingDetails'] = $this->buildShippingDetails($product, $isDigital, $currency, $storeId);
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

    private function buildShippingDetails(
        ProductInterface $product,
        bool $isDigital,
        string $currency,
        ?int $storeId
    ): array {
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

        $carrierRates = $this->carrierRateResolver !== null
            ? $this->carrierRateResolver->resolve($product, $storeId)
            : [];
        if ($carrierRates !== []) {
            $details = [];
            foreach ($carrierRates as $rate) {
                $rateCountry = (string) ($rate['country'] ?? '');
                $details[] = $this->buildPhysicalShipping(
                    number_format(max(0.0, (float) ($rate['cost'] ?? 0.0)), 2, '.', ''),
                    $currency,
                    $rateCountry !== '' ? $rateCountry : $country,
                    (string) ($rate['label'] ?? ''),
                    $storeId
                );
            }

            return count($details) === 1 ? $details[0] : $details;
        }

        return $this->buildPhysicalShipping(
            $this->config->getShippingDefaultRate($storeId),
            $currency,
            $country,
            '',
            $storeId
        );
    }

    private function buildPhysicalShipping(
        string $rate,
        string $currency,
        string $country,
        string $label,
        ?int $storeId
    ): array {
        $details = [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => $rate,
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
        if ($label !== '') {
            $details['shippingLabel'] = $label;
        }

        return $details;
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
