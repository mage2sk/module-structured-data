<?php
declare(strict_types=1);

namespace Panth\StructuredData\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed accessor for `panth_structured_data/structured_data/*`, `panth_structured_data/organization/*`,
 * `panth_structured_data/social_profiles/*`, `panth_structured_data/breadcrumbs/*` and the master
 * `panth_seo/general/*` switches used by this module.
 *
 * This helper is self-contained — it does not depend on the legacy
 * Panth_AdvancedSEO helper. When both modules are installed the config paths
 * are the same (`panth_seo/*`) so behaviour is identical.
 */
class Config
{
    public const XML_GENERAL_ENABLED = 'panth_seo/general/enabled';
    public const XML_GENERAL_DEBUG   = 'panth_seo/general/debug';

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

    public const XML_BREADCRUMBS_PRIORITY_ENABLED = 'panth_structured_data/breadcrumbs/enable_breadcrumb_priority';
    public const XML_BREADCRUMBS_FORMAT           = 'panth_structured_data/breadcrumbs/breadcrumb_format';

    public const XML_SOCIAL_PROFILE_FACEBOOK  = 'panth_structured_data/social_profiles/facebook_url';
    public const XML_SOCIAL_PROFILE_TWITTER   = 'panth_structured_data/social_profiles/twitter_url';
    public const XML_SOCIAL_PROFILE_INSTAGRAM = 'panth_structured_data/social_profiles/instagram_url';
    public const XML_SOCIAL_PROFILE_LINKEDIN  = 'panth_structured_data/social_profiles/linkedin_url';
    public const XML_SOCIAL_PROFILE_YOUTUBE   = 'panth_structured_data/social_profiles/youtube_url';
    public const XML_SOCIAL_PROFILE_PINTEREST = 'panth_structured_data/social_profiles/pinterest_url';
    public const XML_SOCIAL_PROFILE_TIKTOK    = 'panth_structured_data/social_profiles/tiktok_url';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Master switch for structured data output.
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_GENERAL_ENABLED, $storeId);
    }

    /**
     * Whether to emit advisory validator warnings to the log.
     */
    public function isDebug(?int $storeId = null): bool
    {
        return $this->flag(self::XML_GENERAL_DEBUG, $storeId);
    }

    /**
     * Resolve the per-provider enable flag using the same mapping rules used by
     * the legacy Panth_AdvancedSEO helper. Codes that map to numeric/text
     * fields act as implicit toggles (non-empty = enabled).
     */
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

    /**
     * Whether the global ItemList schema on category pages is enabled.
     */
    public function isProductListSchemaEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_SD_PRODUCT_LIST_SCHEMA, $storeId);
    }

    /**
     * Product attribute code used to populate Brand in product JSON-LD.
     */
    public function getBrandAttribute(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_BRAND_ATTRIBUTE, $storeId) ?? 'manufacturer');
    }

    /**
     * Product attribute code for GTIN/EAN/UPC.
     */
    public function getGtinAttribute(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_GTIN_ATTRIBUTE, $storeId) ?? '');
    }

    /**
     * Product attribute code for MPN.
     */
    public function getMpnAttribute(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_MPN_ATTRIBUTE, $storeId) ?? '');
    }

    /**
     * Configured return policy days (implicit enable when > 0).
     */
    public function getReturnPolicyDays(?int $storeId = null): int
    {
        return (int) ($this->value(self::XML_SD_RETURN_POLICY_DAYS, $storeId) ?? 30);
    }

    /**
     * Accepted payment methods textarea (one per line).
     */
    public function getAcceptedPaymentMethods(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_ACCEPTED_PAYMENT, $storeId) ?? '');
    }

    /**
     * Delivery methods textarea (one per line).
     */
    public function getDeliveryMethods(?int $storeId = null): string
    {
        return (string) ($this->value(self::XML_SD_DELIVERY_METHODS, $storeId) ?? '');
    }

    /**
     * Default priceValidUntil (Y-m-d) from config, empty string when unset.
     */
    public function getPriceValidUntilDefault(?int $storeId = null): string
    {
        return trim((string) ($this->value(self::XML_SD_PRICE_VALID_UNTIL_DEFAULT, $storeId) ?? ''));
    }

    /**
     * Configured product condition (schema.org URL form).
     */
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

    /**
     * Return a flat list of non-empty, validated social profile URLs for the
     * Organization sameAs array.
     *
     * @return array<int, string>
     */
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

    /**
     * Generic config accessor for arbitrary paths used by a handful of
     * providers that read fields outside this helper's constants.
     */
    public function getValue(string $path, ?int $storeId = null): mixed
    {
        return $this->value($path, $storeId);
    }

    /**
     * Scheme-allowlist URL validator: http or https only with valid host.
     */
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

    /**
     * @param string $path
     * @param int|null $storeId
     */
    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string $path
     * @param int|null $storeId
     */
    private function value(string $path, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
