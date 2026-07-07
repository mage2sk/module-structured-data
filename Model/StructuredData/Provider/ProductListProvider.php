<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class ProductListProvider extends AbstractProvider
{
    private const MAX_ITEMS = 20;

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly LayerResolver $layerResolver
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'productList';
    }

    public function isApplicable(): bool
    {
        if ($this->getCurrentCategory() === null) {
            return false;
        }

        return $this->config->isProductListSchemaEnabled();
    }

    public function getJsonLd(): array
    {
        $category = $this->getCurrentCategory();
        if ($category === null) {
            return [];
        }

        $items = $this->buildListItems();
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

    private function buildListItems(): array
    {
        try {
            $layer      = $this->layerResolver->get();
            $collection = $layer->getProductCollection();
            $collection->setPageSize(self::MAX_ITEMS);
            $collection->setCurPage(1);

            $collection->getSelect()->distinct(true);
            $products = $collection->getItems();
        } catch (\Throwable) {
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
