<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Psr\Log\LoggerInterface;

class ProductListProvider extends AbstractProvider
{
    private const MAX_ITEMS = 20;

    private const LISTING_ACTIONS = ['catalog_category_view'];

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly Visibility $visibility,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'productList';
    }

    public function isApplicable(): bool
    {
        if (!$this->isCategoryListingPage() || $this->getCurrentCategory() === null) {
            return false;
        }

        return $this->config->isProductListSchemaEnabled();
    }

    public function getJsonLd(): array
    {
        if ($this->getCurrentProduct() !== null) {
            return [];
        }

        $category = $this->getCurrentCategory();
        if (!$category instanceof Category) {
            return [];
        }

        $items = $this->buildListItems($category);
        if ($items === []) {
            return [];
        }

        $categoryUrl = (string) $category->getUrl();

        return [
            '@type'           => 'ItemList',
            '@id'             => $categoryUrl . '#item-list',
            'name'            => (string) $category->getName(),
            'url'             => $categoryUrl,
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];
    }

    private function isCategoryListingPage(): bool
    {
        if ($this->getCurrentProduct() !== null) {
            return false;
        }

        return in_array($this->getFullActionName(), self::LISTING_ACTIONS, true);
    }

    private function buildListItems(Category $category): array
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection->addAttributeToSelect(['name', 'url_key']);
            $collection->addCategoryFilter($category);
            $collection->setVisibility($this->visibility->getVisibleInCatalogIds());
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->addStoreFilter();
            $collection->addUrlRewrite((int) $category->getId());
            $collection->setPageSize(self::MAX_ITEMS);
            $collection->setCurPage(1);

            $collection->getSelect()->distinct(true);
            $collection->getSelect()->order(['cat_index_position ASC', 'e.entity_id ASC']);
            $products = $collection->getItems();
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf(
                    '[Panth_StructuredData] provider "productList" could not load category %s products: %s',
                    (string) $category->getId(),
                    $e->getMessage()
                ),
                ['exception' => $e]
            );

            return [];
        }

        $items    = [];
        $position = 1;

        foreach ($products as $product) {
            $name = (string) $product->getName();
            $url  = (string) $product->getProductUrl();

            if ($name === '' || $url === '') {
                continue;
            }

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'url'      => $url,
                'name'     => $name,
            ];

            $position++;

            if ($position > self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }
}
