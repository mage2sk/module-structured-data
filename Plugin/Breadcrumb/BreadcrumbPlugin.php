<?php
declare(strict_types=1);

namespace Panth\StructuredData\Plugin\Breadcrumb;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config as SeoConfig;
use Psr\Log\LoggerInterface;

class BreadcrumbPlugin
{
    private const XML_PATH_ENABLED = 'panth_structured_data/breadcrumbs/enable_breadcrumb_priority';
    private const XML_PATH_FORMAT  = 'panth_structured_data/breadcrumbs/breadcrumb_format';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly SeoConfig $seoConfig
    ) {
    }

    public function afterGetBreadcrumbPath(CatalogHelper $subject, array $result): array
    {
        if (!$this->isEnabled()) {
            return $result;
        }

        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return $result;
        }

        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return $result;
        }

        $bestPath = $this->resolveBestCategoryPath($categoryIds);
        if ($bestPath === null) {
            return $result;
        }

        return $this->buildBreadcrumbTrail($bestPath, $product);
    }

    private function isEnabled(): bool
    {
        return $this->seoConfig->isEnabled()
            && $this->scopeConfig->isSetFlag(
                self::XML_PATH_ENABLED,
                ScopeInterface::SCOPE_STORE
            );
    }

    private function resolveBestCategoryPath(array $categoryIds): ?array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $format  = $this->getFormat();

        $candidates = [];

        foreach ($categoryIds as $categoryId) {
            try {
                $category = $this->categoryRepository->get((int) $categoryId, $storeId);
            } catch (NoSuchEntityException) {
                continue;
            }

            if (!$category->getIsActive()) {
                continue;
            }

            $ancestors = $this->loadAncestorChain($category, $storeId);
            if ($ancestors === null) {
                continue;
            }

            $totalWeight = 0;
            foreach ($ancestors as $ancestor) {
                $totalWeight += (int) ($ancestor->getCustomAttribute('breadcrumbs_priority')?->getValue() ?? 0);
            }

            $candidates[] = [
                'path'   => $ancestors,
                'weight' => $totalWeight,
                'depth'  => count($ancestors),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function (array $a, array $b) use ($format): int {
            $weightCmp = $b['weight'] <=> $a['weight'];
            if ($weightCmp !== 0) {
                return $weightCmp;
            }

            return match ($format) {
                'shortest' => $a['depth'] <=> $b['depth'],
                'longest'  => $b['depth'] <=> $a['depth'],
                default    => 0,
            };
        });

        return $candidates[0]['path'];
    }

    private function loadAncestorChain(
        \Magento\Catalog\Api\Data\CategoryInterface $category,
        int $storeId
    ): ?array {
        $pathIds = explode('/', (string) $category->getPath());
        $chain   = [];

        foreach ($pathIds as $ancestorId) {
            $ancestorId = (int) $ancestorId;

            try {
                $ancestor = $this->categoryRepository->get($ancestorId, $storeId);
            } catch (NoSuchEntityException) {
                continue;
            }

            if ((int) $ancestor->getLevel() < 2) {
                continue;
            }

            if (!$ancestor->getIsActive()) {
                return null;
            }

            $chain[] = $ancestor;
        }

        return empty($chain) ? null : $chain;
    }

    private function buildBreadcrumbTrail(array $categoryChain, Product $product): array
    {
        $crumbs = [];

        $crumbs['home'] = [
            'label' => __('Home'),
            'title' => null,
            'link'  => $this->storeManager->getStore()->getBaseUrl(),
            'first' => true,
            'last'  => false,
        ];

        foreach ($categoryChain as $index => $category) {
            $crumbs['category' . $category->getId()] = [
                'label' => $category->getName(),
                'title' => null,
                'link'  => $category->getUrl(),
                'first' => false,
                'last'  => false,
            ];
        }

        $crumbs['product'] = [
            'label' => $product->getName(),
            'title' => null,
            'link'  => '',
            'first' => false,
            'last'  => true,
        ];

        return $crumbs;
    }

    private function getFormat(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_FORMAT,
            ScopeInterface::SCOPE_STORE
        );
    }
}
