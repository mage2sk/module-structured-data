<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class ProductProvider extends AbstractProvider
{
    private const XML_LIMITED_STOCK_THRESHOLD = 'panth_structured_data/structured_data/limited_stock_threshold';

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ReviewFactory $reviewFactory,
        private readonly ?StockRegistryInterface $stockRegistry = null,
        private readonly ?ScopeConfigInterface $scopeConfig = null
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'product';
    }

    public function isApplicable(): bool
    {
        return $this->getCurrentProduct() !== null;
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }
        try {
            $store = $this->storeManager->getStore();
            $currency = (string) $store->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = 'USD';
        }

        $url = (string) $product->getProductUrl();
        $image = $this->buildImages($product);

        $node = [
            '@type' => 'Product',
            '@id'   => $url . '#product',
            'name'  => (string) $product->getName(),
            'sku'   => (string) $product->getSku(),
            'url'   => $url,
        ];
        if ($image !== []) {
            $node['image'] = count($image) === 1 ? $image[0] : $image;
        }

        $description = trim(strip_tags((string) ($product->getData('meta_description') ?: $product->getData('short_description') ?: $product->getData('description'))));
        if ($description !== '') {
            $node['description'] = mb_substr($description, 0, 5000);
        }

        $mpnAttr = $this->config->getMpnAttribute() ?: 'mpn';
        $mpn = (string) ($product->getData($mpnAttr) ?? '');
        if ($mpn !== '') {
            $node['mpn'] = $mpn;
        }
        $gtinAttr = $this->config->getGtinAttribute();
        $gtin = $gtinAttr !== ''
            ? (string) ($product->getData($gtinAttr) ?? '')
            : (string) ($product->getData('gtin') ?? $product->getData('gtin13') ?? $product->getData('ean') ?? '');
        if ($gtin !== '') {
            $node['gtin'] = $gtin;
        }

        $brandAttr = $this->config->getBrandAttribute() ?: 'manufacturer';
        $brandName = $this->coerceAttributeText($product->getAttributeText($brandAttr));
        if ($brandName === '') {
            $brandName = trim((string) ($product->getData('brand') ?? ''));
        }
        if ($brandName !== '') {
            $node['brand'] = [
                '@type' => 'Brand',
                'name'  => $brandName,
            ];
        }

        $audience = $this->coerceAttributeText($product->getAttributeText('gender'));
        if ($audience === '') {
            $audience = trim((string) ($product->getData('target_audience') ?? ''));
        }
        if ($audience !== '') {
            $node['audience'] = [
                '@type' => 'PeopleAudience',
                'audienceType' => $audience,
            ];
        }

        $offer = $this->buildOffer($product, $currency, $url);
        if ($offer !== []) {
            $node['offers'] = $offer;
        }

        $rating = $this->buildRating($product);
        if ($rating !== []) {
            $node['aggregateRating'] = $rating;
        }

        $dateModified = $this->formatIso8601((string) ($product->getData('updated_at') ?? ''));
        if ($dateModified !== '') {
            $node['dateModified'] = $dateModified;
        }
        $datePublished = $this->formatIso8601((string) ($product->getData('created_at') ?? ''));
        if ($datePublished !== '') {
            $node['datePublished'] = $datePublished;
        }

        return $node;
    }

    private function coerceAttributeText(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_array($value)) {
            $parts = array_map(
                static fn ($v): string => is_scalar($v) ? trim((string) $v) : '',
                $value
            );
            return trim(implode(', ', array_filter($parts, static fn ($v): bool => $v !== '')));
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }
        return '';
    }

    private function formatIso8601(string $datetime): string
    {
        if ($datetime === '') {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($datetime);
            return $dt->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildImages(ProductInterface $product): array
    {
        $urls = [];
        try {
            $main = $this->imageHelper->init($product, 'product_base_image')->getUrl();
            if ($main !== '') {
                $urls[] = $main;
            }
        } catch (\Throwable) {
        }

        try {
            $gallery = $product->getMediaGalleryImages();
            if ($gallery && method_exists($gallery, 'getItems')) {
                foreach ($gallery->getItems() as $item) {
                    $u = (string) $item->getUrl();
                    if ($u !== '' && !in_array($u, $urls, true)) {
                        $urls[] = $u;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $urls;
    }

    private function buildOffer(ProductInterface $product, string $currency, string $url): array
    {
        $typeId = (string) $product->getTypeId();
        $isVariantType = in_array($typeId, ['configurable', 'bundle', 'grouped'], true);

        $finalPrice = $product->getFinalPrice();
        if ($finalPrice === null || $finalPrice === false) {
            try {
                $finalPrice = (float) $product->getPriceInfo()->getPrice('final_price')->getValue();
            } catch (\Throwable) {
                $finalPrice = 0.0;
            }
        }
        $finalPrice = (float) $finalPrice;

        if (!$isVariantType && $finalPrice <= 0.0) {
            return [];
        }

        $offer = ['@type' => 'Offer'];
        if (!$isVariantType) {
            $offer['url']           = $url;
            $offer['price']         = number_format($finalPrice, 2, '.', '');
            $offer['priceCurrency'] = $currency;
            $offer['availability']  = $this->resolveAvailability($product);
        }
        $offer['itemCondition'] = $this->config->getProductConditionSchemaUrl();
        $offer['seller']        = ['@id' => rtrim($this->getBaseUrl(), '/') . '/#organization'];

        $priceValidUntil = $this->resolvePriceValidUntil($product);
        if ($priceValidUntil !== '') {
            $offer['priceValidUntil'] = $priceValidUntil;
        }

        if ($this->config->getReturnPolicyDays() <= 0) {
            $offer['hasMerchantReturnPolicy'] = [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'US',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => 30,
                'returnMethod' => 'https://schema.org/ReturnByMail',
                'returnFees' => 'https://schema.org/FreeReturn',
            ];
        }

        if (trim($this->config->getDeliveryMethods()) === '') {
            $offer['shippingDetails'] = [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => [
                    '@type' => 'MonetaryAmount',
                    'value' => '0',
                    'currency' => $currency,
                ],
                'shippingDestination' => [
                    '@type' => 'DefinedRegion',
                    'addressCountry' => 'US',
                ],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => 0,
                        'maxValue' => 1,
                        'unitCode' => 'DAY',
                    ],
                    'transitTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => 2,
                        'maxValue' => 5,
                        'unitCode' => 'DAY',
                    ],
                ],
            ];
        }

        return $offer;
    }

    private function resolvePriceValidUntil(ProductInterface $product): string
    {
        $specialTo = (string) ($product->getSpecialToDate() ?? '');
        if ($specialTo !== '') {
            try {
                $ts = strtotime($specialTo);
                if ($ts !== false && $ts > time()) {
                    return date('Y-m-d', $ts);
                }
            } catch (\Throwable) {
            }
        }

        $configDefault = $this->config->getPriceValidUntilDefault();
        if ($configDefault !== '') {
            try {
                $ts = strtotime($configDefault);
                if ($ts !== false) {
                    return date('Y-m-d', $ts);
                }
            } catch (\Throwable) {
            }
        }

        return date('Y-m-d', strtotime('+1 year'));
    }

    private function buildRating(ProductInterface $product): array
    {
        try {
            $hadOriginal = $product->hasData('rating_summary');
            $original = $hadOriginal ? $product->getData('rating_summary') : null;

            $review = $this->reviewFactory->create();
            $review->getEntitySummary($product, (int) $this->storeManager->getStore()->getId());
            $summary = $product->getRatingSummary();

            if ($hadOriginal) {
                $product->setData('rating_summary', $original);
            } else {
                $product->unsetData('rating_summary');
            }

            if (!$summary instanceof \Magento\Framework\DataObject) {
                return [];
            }
            $reviewCount = (int) $summary->getReviewsCount();
            $rating = (float) $summary->getRatingSummary();
            if ($reviewCount <= 0 || $rating <= 0) {
                return [];
            }
            return [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($rating / 20.0, 2, '.', ''),
                'bestRating' => '5',
                'worstRating' => '1',
                'reviewCount' => $reviewCount,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveAvailability(ProductInterface $product): string
    {
        try {
            $status = (int) $product->getStatus();
            $visibility = (int) $product->getVisibility();
            if ($status === \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED
                || $visibility === \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE
            ) {
                return 'https://schema.org/Discontinued';
            }

            $newsFrom = (string) ($product->getData('news_from_date') ?? '');
            if ($newsFrom !== '') {
                $ts = strtotime($newsFrom);
                if ($ts !== false && $ts > time()) {
                    return 'https://schema.org/PreOrder';
                }
            }

            if ($this->stockRegistry !== null) {
                $stockItem = $this->stockRegistry->getStockItem(
                    (int) $product->getId()
                );

                $qty = (float) $stockItem->getQty();
                $isInStock = (bool) $stockItem->getIsInStock();
                $backorders = (int) $stockItem->getBackorders();

                if (!$isInStock && $backorders > 0) {
                    return 'https://schema.org/BackOrder';
                }
                if ($isInStock && $qty <= 0 && $backorders > 0) {
                    return 'https://schema.org/BackOrder';
                }

                if (!$isInStock) {
                    return 'https://schema.org/OutOfStock';
                }

                $threshold = $this->getLimitedStockThreshold();
                if ($qty > 0 && $qty < $threshold) {
                    return 'https://schema.org/LimitedAvailability';
                }

                if ($product->isSalable() || $isInStock) {
                    return 'https://schema.org/InStock';
                }
            }

            if ($product->isAvailable()) {
                return 'https://schema.org/InStock';
            }
        } catch (\Throwable) {
        }

        return 'https://schema.org/OutOfStock';
    }

    private function getLimitedStockThreshold(): int
    {
        if ($this->scopeConfig === null) {
            return 5;
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = null;
        }

        $value = $this->scopeConfig->getValue(
            self::XML_LIMITED_STOCK_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== null ? max(1, (int) $value) : 5;
    }
}
