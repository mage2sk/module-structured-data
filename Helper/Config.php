<?php
declare(strict_types=1);

namespace Panth\StructuredData\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_GENERAL_ENABLED = 'panth_seo/general/enabled';
    public const XML_GENERAL_DEBUG   = 'panth_seo/general/debug';

    public const XML_SD_MERCHANT_FIELDS_ENABLED   = 'panth_structured_data/structured_data/merchant_fields_enabled';
    public const XML_SD_MERCHANT_RETURN_ENABLED   = 'panth_structured_data/structured_data/merchant_return_enabled';
    public const XML_SD_RETURN_APPLICABLE_COUNTRY = 'panth_structured_data/structured_data/return_applicable_country';
    public const XML_SD_RETURN_METHOD             = 'panth_structured_data/structured_data/return_method';
    public const XML_SD_RETURN_FEES               = 'panth_structured_data/structured_data/return_policy_fees';
    public const XML_SD_MERCHANT_SHIPPING_ENABLED = 'panth_structured_data/structured_data/merchant_shipping_enabled';
    public const XML_SD_SHIPPING_DEFAULT_RATE     = 'panth_structured_data/structured_data/shipping_default_rate';
    public const XML_SD_SHIPPING_HANDLING_MIN     = 'panth_structured_data/structured_data/shipping_handling_min';
    public const XML_SD_SHIPPING_HANDLING_MAX     = 'panth_structured_data/structured_data/shipping_handling_max';
    public const XML_SD_SHIPPING_TRANSIT_MIN      = 'panth_structured_data/structured_data/shipping_transit_min';
    public const XML_SD_SHIPPING_TRANSIT_MAX      = 'panth_structured_data/structured_data/shipping_transit_max';
    public const XML_SD_SHIPPING_COUNTRY          = 'panth_structured_data/structured_data/shipping_country';
    public const XML_SD_BRAND_STORE_FALLBACK      = 'panth_structured_data/structured_data/brand_use_store_name_fallback';
    public const XML_STORE_COUNTRY                = 'general/country/default';
    public const XML_STORE_NAME                   = 'general/store_information/name';
    public const XML_SD_RETURN_POLICY_DAYS        = 'panth_structured_data/structured_data/return_policy_days';
    public const XML_SD_BRAND_ATTRIBUTE           = 'panth_structured_data/structured_data/brand_attribute';
    public const XML_SD_GTIN_ATTRIBUTE            = 'panth_structured_data/structured_data/gtin_attribute';
    public const XML_SD_MPN_ATTRIBUTE             = 'panth_structured_data/structured_data/mpn_attribute';
    public const XML_SD_PRODUCT_LIST_SCHEMA       = 'panth_structured_data/structured_data/enable_product_list_schema';
    public const XML_SD_ACCEPTED_PAYMENT          = 'panth_structured_data/structured_data/accepted_payment_methods';
    public const XML_SD_DELIVERY_METHODS          = 'panth_structured_data/structured_data/delivery_methods';
    public const XML_SD_PRODUCT_CONDITION         = 'panth_structured_data/structured_data/product_condition';
    public const XML_SD_PRICE_VALID_UNTIL_DEFAULT = 'panth_structured_data/structured_data/price_valid_until_default';
    public const XML_SD_CUSTOM_PROPERTIES         = 'panth_structured_data/structured_data/custom_properties';
    public const XML_SD_DEFAULT_BRAND             = 'panth_structured_data/structured_data/default_brand';

    public const XML_BREADCRUMBS_PRIORITY_ENABLED = 'panth_structured_data/breadcrumbs/enable_breadcrumb_priority';
    public const XML_BREADCRUMBS_FORMAT           = 'panth_structured_data/breadcrumbs/breadcrumb_format';

    public const XML_SOCIAL_PROFILE_FACEBOOK  = 'panth_structured_data/social_profiles/facebook_url';
    public const XML_SOCIAL_PROFILE_TWITTER   = 'panth_structured_data/social_profiles/twitter_url';
    public const XML_SOCIAL_PROFILE_INSTAGRAM = 'panth_structured_data/social_profiles/instagram_url';
    public const XML_SOCIAL_PROFILE_LINKEDIN  = 'panth_structured_data/social_profiles/linkedin_url';
    public const XML_SOCIAL_PROFILE_YOUTUBE   = 'panth_structured_data/social_profiles/youtube_url';
    public const XML_SOCIAL_PROFILE_PINTEREST = 'panth_structured_data/social_profiles/pinterest_url';
    public const XML_SOCIAL_PROFILE_TIKTOK    = 'panth_structured_data/social_profiles/tiktok_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_GENERAL_ENABLED, $storeId);
    }

    public function isDebug(?int $storeId = null): bool
    {
        return $this->flag(self::XML_GENERAL_DEBUG, $storeId);
    }

    public function isStructuredDataEnabled(string $code, ?int $storeId = null): bool
    {
        static $codeToConfigKey = [
            'return_policy'      => 'return_policy_days',
            'configurable_offer' => 'configurable_multi_offer',
            'productList'        => 'enable_product_list_schema',
            'product_group'      => 'product_group_enabled',
            'pros_cons'          => 'pros_cons_enabled',
            'bundle_offer'       => 'product',
            'grouped_offer'      => 'product',
            'deliveryMethod'     => 'delivery_methods',
            'paymentMethod'      => 'accepted_payment_methods',
            'custom_properties'  => 'custom_properties',
        ];

        $configKey = $codeToConfigKey[$code] ?? $code;
        $path = 'panth_structured_data/structured_data/' . $configKey;

        if (in_array(
            $code,
            ['return_policy', 'deliveryMethod', 'paymentMethod', 'custom_properties'],
            true
        )) {
            $val = $this->value($path, $storeId);
            return $val !== null && $val !== '' && $val !== '0';
        }

        return $this->flag($path, $storeId);
    }

    public function isProductListSchemaEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_SD_PRODUCT_LIST_SCHEMA, $storeId);
    }

    public function getBrandAttribute(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_BRAND_ATTRIBUTE, $storeId) ?? 'manufacturer');
    }

    public function getGtinAttribute(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_GTIN_ATTRIBUTE, $storeId) ?? '');
    }

    public function getMpnAttribute(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_MPN_ATTRIBUTE, $storeId) ?? '');
    }

    public function getReturnPolicyDays(?int $storeId = null): int
    {
        return (int) ($this->value(self::XML_SD_RETURN_POLICY_DAYS, $storeId) ?? 30);
    }

    public function getAcceptedPaymentMethods(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_ACCEPTED_PAYMENT, $storeId) ?? '');
    }

    public function getDeliveryMethods(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_DELIVERY_METHODS, $storeId) ?? '');
    }

    public function getPriceValidUntilDefault(?int $storeId = null): string
    {
        return trim((string) ($this->value(self::XML_SD_PRICE_VALID_UNTIL_DEFAULT, $storeId) ?? ''));
    }

    public function getDefaultBrand(?int $storeId = null): string
    {
        return trim((string) ($this->value(self::XML_SD_DEFAULT_BRAND, $storeId) ?? ''));
    }

    public function getProductConditionSchemaUrl(?int $storeId = null): string
    {
        $map = [
            'new'         => 'https://schema.org/NewCondition',
            'used'        => 'https://schema.org/UsedCondition',
            'refurbished' => 'https://schema.org/RefurbishedCondition',
            'damaged'     => 'https://schema.org/DamagedCondition',
        ];
        $value = (string) ($this->value(self::XML_SD_PRODUCT_CONDITION, $storeId) ?? 'new');
        return $map[$value] ?? 'https://schema.org/NewCondition';
    }

    public function getSocialProfileUrls(?int $storeId = null): array
    {
        $paths = [
            self::XML_SOCIAL_PROFILE_FACEBOOK,
            self::XML_SOCIAL_PROFILE_TWITTER,
            self::XML_SOCIAL_PROFILE_INSTAGRAM,
            self::XML_SOCIAL_PROFILE_LINKEDIN,
            self::XML_SOCIAL_PROFILE_YOUTUBE,
            self::XML_SOCIAL_PROFILE_PINTEREST,
            self::XML_SOCIAL_PROFILE_TIKTOK,
        ];

        $urls = [];
        foreach ($paths as $path) {
            $url = trim((string) ($this->value($path, $storeId) ?? ''));
            if ($url === '' || !$this->isSafeHttpUrl($url)) {
                continue;
            }
            $urls[] = $url;
        }

        return $urls;
    }

    public function getValue(string $path, ?int $storeId = null): mixed
    {
        return $this->value($path, $storeId);
    }

    private function isSafeHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        return $host !== '';
    }

    public function isMerchantFieldsEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_SD_MERCHANT_FIELDS_ENABLED, $storeId);
    }

    public function isMerchantReturnEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_SD_MERCHANT_RETURN_ENABLED, $storeId);
    }

    public function isMerchantShippingEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_SD_MERCHANT_SHIPPING_ENABLED, $storeId);
    }

    public function isBrandStoreNameFallbackEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_SD_BRAND_STORE_FALLBACK, $storeId);
    }

    public function getStoreCountry(?int $storeId = null): string
    {
        $country = trim((string) ($this->value(self::XML_STORE_COUNTRY, $storeId) ?? ''));

        return $country !== '' ? $country : 'US';
    }

    public function getReturnApplicableCountry(?int $storeId = null): string
    {
        $country = trim((string) ($this->value(self::XML_SD_RETURN_APPLICABLE_COUNTRY, $storeId) ?? ''));

        return $country !== '' ? $country : $this->getStoreCountry($storeId);
    }

    public function getShippingCountry(?int $storeId = null): string
    {
        $country = trim((string) ($this->value(self::XML_SD_SHIPPING_COUNTRY, $storeId) ?? ''));

        return $country !== '' ? $country : $this->getStoreCountry($storeId);
    }

    public function getReturnMethodSchemaUrl(?int $storeId = null): string
    {
        $map = [
            'bymail' => 'https://schema.org/ReturnByMail',
            'instore' => 'https://schema.org/ReturnInStore',
            'kiosk' => 'https://schema.org/ReturnAtKiosk',
        ];
        $value = strtolower(trim((string) ($this->value(self::XML_SD_RETURN_METHOD, $storeId) ?? 'bymail')));

        return $map[$value] ?? 'https://schema.org/ReturnByMail';
    }

    public function getReturnFeesSchemaUrl(?int $storeId = null): string
    {
        $lower = strtolower(trim((string) ($this->value(self::XML_SD_RETURN_FEES, $storeId) ?? '')));
        if ($lower === '' || $lower === 'free' || $lower === 'freereturn') {
            return 'https://schema.org/FreeReturn';
        }

        $enum = [
            'returnfeescustomerresponsibility' => 'https://schema.org/ReturnFeesCustomerResponsibility',
            'returnshippingfees' => 'https://schema.org/ReturnShippingFees',
            'restockingfees' => 'https://schema.org/RestockingFees',
        ];

        return $enum[$lower] ?? 'https://schema.org/FreeReturn';
    }

    public function getShippingDefaultRate(?int $storeId = null): string
    {
        $raw = $this->value(self::XML_SD_SHIPPING_DEFAULT_RATE, $storeId);

        return number_format(max(0.0, (float) ($raw ?? 0)), 2, '.', '');
    }

    public function getShippingHandlingMin(?int $storeId = null): int
    {
        return max(0, (int) ($this->value(self::XML_SD_SHIPPING_HANDLING_MIN, $storeId) ?? 0));
    }

    public function getShippingHandlingMax(?int $storeId = null): int
    {
        return max(
            $this->getShippingHandlingMin($storeId),
            (int) ($this->value(self::XML_SD_SHIPPING_HANDLING_MAX, $storeId) ?? 1)
        );
    }

    public function getShippingTransitMin(?int $storeId = null): int
    {
        return max(0, (int) ($this->value(self::XML_SD_SHIPPING_TRANSIT_MIN, $storeId) ?? 1));
    }

    public function getShippingTransitMax(?int $storeId = null): int
    {
        return max(
            $this->getShippingTransitMin($storeId),
            (int) ($this->value(self::XML_SD_SHIPPING_TRANSIT_MAX, $storeId) ?? 5)
        );
    }

    public function getStoreName(?int $storeId = null): string
    {
        return trim((string) ($this->value(self::XML_STORE_NAME, $storeId) ?? ''));
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
