<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Shipping;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\StructuredData\Helper\Config;

class ShippingDetailsBuilder
{
    private const XML_STORE_COUNTRY     = 'general/country/default';
    private const XML_HANDLING_TIME_MIN = 'panth_structured_data/structured_data/handling_time_min';
    private const XML_HANDLING_TIME_MAX = 'panth_structured_data/structured_data/handling_time_max';

    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function build(string $currency, ?int $storeId = null): array
    {
        $entries = $this->parseDeliveryLines($storeId);
        if ($entries === []) {
            return [];
        }

        $country = $this->getStoreCountry($storeId);

        $details = [];
        foreach ($entries as $entry) {
            $details[] = $this->buildShippingDetail($entry, $currency, $country, $storeId);
        }

        return $details;
    }

    private function parseDeliveryLines(?int $storeId): array
    {
        $raw = trim($this->config->getDeliveryMethods($storeId));
        if ($raw === '') {
            return [];
        }

        $rows = preg_split('/\r?\n/', $raw);
        if ($rows === false) {
            return [];
        }

        $entries = [];
        foreach ($rows as $row) {
            $row = trim($row);
            if ($row === '' || str_starts_with($row, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $row));

            $name = $parts[0] ?? '';
            if ($name === '') {
                continue;
            }

            if (count($parts) >= 6) {
                $handlingMin = max(0, (int) $parts[1]);
                $handlingMax = max($handlingMin, (int) $parts[2]);
                $transitMin  = max(0, (int) $parts[3]);
                $transitMax  = max($transitMin, (int) $parts[4]);
                $cost        = number_format(max(0.0, (float) $parts[5]), 2, '.', '');
            } else {
                $handlingMin = null;
                $handlingMax = null;
                $transitMin  = isset($parts[1]) ? max(0, (int) $parts[1]) : 0;
                $transitMax  = isset($parts[2]) ? max($transitMin, (int) $parts[2]) : $transitMin;
                $cost        = isset($parts[3]) ? number_format(max(0.0, (float) $parts[3]), 2, '.', '') : '0.00';
            }

            $entries[] = [
                'name'        => $name,
                'handlingMin' => $handlingMin,
                'handlingMax' => $handlingMax,
                'transitMin'  => $transitMin,
                'transitMax'  => $transitMax,
                'cost'        => $cost,
            ];
        }

        return $entries;
    }

    private function buildShippingDetail(array $entry, string $currency, string $country, ?int $storeId): array
    {
        $deliveryTime = ['@type' => 'ShippingDeliveryTime'];

        $handling = $this->resolveHandlingTime($entry, $storeId);
        if ($handling !== null) {
            $deliveryTime['handlingTime'] = [
                '@type'    => 'QuantitativeValue',
                'minValue' => $handling['min'],
                'maxValue' => $handling['max'],
                'unitCode' => 'DAY',
            ];
        }

        $deliveryTime['transitTime'] = [
            '@type'    => 'QuantitativeValue',
            'minValue' => $entry['transitMin'],
            'maxValue' => $entry['transitMax'],
            'unitCode' => 'DAY',
        ];

        $deliveryTime['businessDays'] = [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => [
                'https://schema.org/Monday',
                'https://schema.org/Tuesday',
                'https://schema.org/Wednesday',
                'https://schema.org/Thursday',
                'https://schema.org/Friday',
            ],
        ];

        return [
            '@type'        => 'OfferShippingDetails',
            'shippingRate' => [
                '@type'    => 'MonetaryAmount',
                'value'    => $entry['cost'],
                'currency' => $currency,
            ],
            'shippingDestination' => [
                '@type'          => 'DefinedRegion',
                'addressCountry' => $country,
            ],
            'deliveryTime'  => $deliveryTime,
            'shippingLabel' => $entry['name'],
        ];
    }

    private function resolveHandlingTime(array $entry, ?int $storeId): ?array
    {
        if ($entry['handlingMin'] !== null && $entry['handlingMax'] !== null) {
            return ['min' => $entry['handlingMin'], 'max' => $entry['handlingMax']];
        }

        $min = $this->scopeConfig->getValue(
            self::XML_HANDLING_TIME_MIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $max = $this->scopeConfig->getValue(
            self::XML_HANDLING_TIME_MAX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($min === null && $max === null) {
            return null;
        }

        $minValue = max(0, (int) ($min ?? 0));
        $maxValue = max($minValue, (int) ($max ?? $minValue));

        return ['min' => $minValue, 'max' => $maxValue];
    }

    private function getStoreCountry(?int $storeId): string
    {
        $country = (string) ($this->scopeConfig->getValue(
            self::XML_STORE_COUNTRY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '');

        return $country !== '' ? $country : 'US';
    }
}
