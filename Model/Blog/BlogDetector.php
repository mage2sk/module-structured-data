<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\Blog;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class BlogDetector
{
    private array $supportedClasses;

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resource,
        array $supportedClasses = []
    ) {
        $this->supportedClasses = $supportedClasses;
    }

    public function isBlogInstalled(): bool
    {
        foreach ($this->supportedClasses as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        return false;
    }

    public function getBlogPosts(int $storeId): array
    {
        $posts = [];

        try {
            $store   = $this->storeManager->getStore($storeId);
            $baseUrl = rtrim((string) $store->getBaseUrl(), '/') . '/';

            foreach ($this->supportedClasses as $class) {
                if (!class_exists($class)) {
                    continue;
                }

                $posts = $this->loadPostsFromModule($class, $storeId, $baseUrl);
                break;
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Panth SEO BlogDetector failed to load blog posts',
                ['error' => $e->getMessage()]
            );
        }

        return $posts;
    }

    private function loadPostsFromModule(string $modelClass, int $storeId, string $baseUrl): array
    {
        $posts = [];

        try {
            $collectionFactory = $this->resolveCollectionFactory($modelClass);
            if ($collectionFactory === null) {
                return [];
            }

            $collection = $collectionFactory->create();

            if (method_exists($collection, 'addStoreFilter')) {
                $collection->addStoreFilter($storeId);
            }

            if (method_exists($collection, 'addFieldToFilter')) {
                $activeColumn = $this->resolveActiveColumn($collection);
                if ($activeColumn !== null) {
                    $collection->addFieldToFilter($activeColumn, 1);
                }
            }
            if (method_exists($collection, 'addActiveFilter')) {
                $collection->addActiveFilter();
            }

            foreach ($collection as $post) {
                $url   = $this->resolvePostUrl($post, $baseUrl);
                $title = $this->resolvePostTitle($post);

                if ($url !== '' && $title !== '') {
                    $posts[] = [
                        'url'   => $url,
                        'title' => $title,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Panth SEO BlogDetector: collection load failed',
                ['class' => $modelClass, 'error' => $e->getMessage()]
            );
        }

        return $posts;
    }

    private function resolveCollectionFactory(string $modelClass): ?object
    {
        $parts     = explode('\\', $modelClass);
        $modelName = array_pop($parts);
        $namespace  = implode('\\', $parts);

        $collectionFactoryClass = $namespace . '\\ResourceModel\\' . $modelName . '\\CollectionFactory';

        if (!class_exists($collectionFactoryClass)) {
            return null;
        }

        return $this->objectManager->get($collectionFactoryClass);
    }

    private function resolveActiveColumn(object $collection): ?string
    {
        try {
            $mainTable = null;
            if (method_exists($collection, 'getMainTable')) {
                $mainTable = (string) $collection->getMainTable();
            }
            if (($mainTable === null || $mainTable === '') && method_exists($collection, 'getResource')) {
                $resource = $collection->getResource();
                if ($resource !== null && method_exists($resource, 'getMainTable')) {
                    $mainTable = (string) $resource->getMainTable();
                }
            }
            if ($mainTable === null || $mainTable === '') {
                return null;
            }
            $columns = $this->resource->getConnection()->describeTable($mainTable);
            foreach (['is_active', 'enabled', 'status'] as $candidate) {
                if (isset($columns[$candidate])) {
                    return $candidate;
                }
            }
        } catch (\Throwable) {
        }
        return null;
    }

    private function resolvePostUrl(object $post, string $baseUrl): string
    {
        if (method_exists($post, 'getPostUrl')) {
            $url = (string) $post->getPostUrl();
            if ($url !== '') {
                return $url;
            }
        }

        if (method_exists($post, 'getUrl')) {
            $url = (string) $post->getUrl();
            if ($url !== '') {
                return $url;
            }
        }

        $identifier = '';
        if (method_exists($post, 'getIdentifier')) {
            $identifier = (string) $post->getIdentifier();
        } elseif (method_exists($post, 'getUrlKey')) {
            $identifier = (string) $post->getUrlKey();
        }

        if ($identifier !== '') {
            return $baseUrl . 'blog/' . ltrim($identifier, '/');
        }

        return '';
    }

    private function resolvePostTitle(object $post): string
    {
        if (method_exists($post, 'getTitle')) {
            return (string) $post->getTitle();
        }

        if (method_exists($post, 'getName')) {
            return (string) $post->getName();
        }

        return '';
    }
}
