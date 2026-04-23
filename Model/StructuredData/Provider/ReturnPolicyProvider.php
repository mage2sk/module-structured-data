<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

/**
 * Emits a MerchantReturnPolicy schema.org node on product pages.
 *
 * Config fields used:
 *  - panth_structured_data/structured_data/return_policy_days  (int, must be > 0 to activate)
 *  - panth_structured_data/structured_data/return_policy_type  (refund|exchange)
 *  - panth_structured_data/structured_data/return_policy_fees  (free|custom string)
 *
 * The applicable country is read from the store's country configuration.
 */
class ReturnPolicyProvider extends AbstractProvider
{
    private const XML_RETURN_DAYS = 'panth_structured_data/structured_data/return_policy_days';
    private const XML_RETURN_TYPE = 'panth_structured_data/structured_data/return_policy_type';
    private const XML_RETURN_FEES = 'panth_structured_data/structured_data/return_policy_fees';
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
        return 'return_policy';
    }

    public function isApplicable(): bool
    {
        if ($this->getCurrentProduct() === null) {
            return false;
        }

        return $this->getReturnDays() > 0;
    }

    public function getJsonLd(): array
    {
        $days = $this->getReturnDays();
        if ($days <= 0) {
            return [];
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = null;
        }

        $country = $this->getStoreCountry($storeId);
        $returnFees = $this->getReturnFees($storeId);

        $node = [
            '@type'                => 'MerchantReturnPolicy',
            'applicableCountry'    => $country,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'   => $days,
            'returnMethod'         => 'https://schema.org/ReturnByMail',
            'returnFees'           => $returnFees,
        ];

        $returnType = $this->getReturnType($storeId);
        if ($returnType !== '') {
            // Schema.org doesn't have a direct "refund vs exchange" field,
            // but we include it as an additional property for richer data.
            $node['additionalProperty'] = [
                '@type' => 'PropertyValue',
                'name'  => 'Return Type',
                'value' => $returnType,
            ];
        }

        return $node;
    }

    private function getReturnDays(): int
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = null;
        }

        return (int) ($this->scopeConfig->getValue(
            self::XML_RETURN_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? 0);
    }

    private function getReturnType(?int $storeId): string
    {
        $value = (string) ($this->scopeConfig->getValue(
            self::XML_RETURN_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '');

        return $value;
    }

    /**
     * Schema.org's `returnFees` only accepts values from the `ReturnFeesEnumeration`
     * (FreeReturn / ReturnFeesCustomerResponsibility / ReturnShippingFees /
     * RestockingFees). Any other string — including a plain "5.99" or the
     * merchant's free-text "Paid by customer" — will fail Google's Rich Results
     * validator. We only recognise the safe `free` alias and fall back to
     * `ReturnFeesCustomerResponsibility` for anything else.
     */
    private function getReturnFees(?int $storeId): string
    {
        $value = (string) ($this->scopeConfig->getValue(
            self::XML_RETURN_FEES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '');

        $lower = strtolower(trim($value));
        if ($lower === '' || $lower === 'free' || $lower === 'freereturn') {
            return 'https://schema.org/FreeReturn';
        }

        $enum = [
            'returnfeescustomerresponsibility' => 'https://schema.org/ReturnFeesCustomerResponsibility',
            'returnshippingfees'               => 'https://schema.org/ReturnShippingFees',
            'restockingfees'                   => 'https://schema.org/RestockingFees',
        ];
        if (isset($enum[$lower])) {
            return $enum[$lower];
        }

        // Non-standard admin-entered string (e.g. "$5.99 restocking fee") maps
        // to the catch-all customer-responsibility enum so the payload still
        // validates. The human-readable description belongs in returnPolicyBody
        // if the merchant wants it — not as the enum value itself.
        return 'https://schema.org/ReturnFeesCustomerResponsibility';
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
