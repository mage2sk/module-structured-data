<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class DeliveryMethodProvider extends AbstractProvider
{
    private const XML_STORE_COUNTRY = 'general/country/default';

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
        return 'deliveryMethod';
    }

    public function isApplicable(): bool
    {
        if ($this->getCurrentProduct() === null) {
            return false;
        }

        return $this->parseDeliveryLines() !== [];
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }

        $lines = $this->parseDeliveryLines();
        if ($lines === []) {
            return [];
        }

        try {
            $store    = $this->storeManager->getStore();
            $storeId  = (int) $store->getId();
            $currency = (string) $store->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = 'USD';
            $storeId  = null;
        }

        $country = $this->getStoreCountry($storeId);
        $url     = (string) $product->getProductUrl();

        $shippingDetails = [];
        foreach ($lines as $entry) {
            $shippingDetails[] = $this->buildShippingDetail($entry, $currency, $country);
        }

        return [
            '@type'           => 'Offer',
            '@id'             => $url . '#offer-shipping',
            'shippingDetails' => count($shippingDetails) === 1
                ? $shippingDetails[0]
                : $shippingDetails,
        ];
    }

    private function parseDeliveryLines(): array
    {
        $raw = $this->config->getDeliveryMethods();
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

            $minDays = isset($parts[1]) ? max(0, (int) $parts[1]) : 0;
            $maxDays = isset($parts[2]) ? max($minDays, (int) $parts[2]) : $minDays;
            $cost    = isset($parts[3]) ? number_format(max(0.0, (float) $parts[3]), 2, '.', '') : '0.00';

            $entries[] = [
                'name'    => $name,
                'minDays' => $minDays,
                'maxDays' => $maxDays,
                'cost'    => $cost,
            ];
        }

        return $entries;
    }

    private function buildShippingDetail(array $entry, string $currency, string $country): array
    {
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
            'deliveryTime' => [
                '@type'       => 'ShippingDeliveryTime',
                'transitTime' => [
                    '@type'    => 'QuantitativeValue',
                    'minValue' => $entry['minDays'],
                    'maxValue' => $entry['maxDays'],
                    'unitCode' => 'DAY',
                ],
                'businessDays' => [
                    '@type'    => 'OpeningHoursSpecification',
                    'dayOfWeek' => [
                        'https://schema.org/Monday',
                        'https://schema.org/Tuesday',
                        'https://schema.org/Wednesday',
                        'https://schema.org/Thursday',
                        'https://schema.org/Friday',
                    ],
                ],
            ],
            'shippingLabel' => $entry['name'],
        ];
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
