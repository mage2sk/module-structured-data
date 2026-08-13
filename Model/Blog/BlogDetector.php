<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\Blog;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class BlogDetector
{
    private const POST_VIEW_ACTION_CLASSES = [
        'mpblog_post_view' => ['Mageplaza\Blog\Model\Post'],
        'blog_post_view'   => ['Magefan\Blog\Model\Post', 'Mirasvit\Blog\Model\Post'],
    ];

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

    public function getPostById(string $fullActionName, int $postId): ?object
    {
        if ($postId <= 0) {
            return null;
        }

        $candidates = self::POST_VIEW_ACTION_CLASSES[strtolower($fullActionName)] ?? [];
        foreach ($candidates as $class) {
            if (!in_array($class, $this->supportedClasses, true) || !class_exists($class)) {
                continue;
            }

            $post = $this->loadPostById($class, $postId);
            if ($post !== null) {
                return $post;
            }
        }

        return null;
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

    private function loadPostById(string $modelClass, int $postId): ?object
    {
        try {
            $collectionFactory = $this->resolveCollectionFactory($modelClass);
            if ($collectionFactory === null) {
                return null;
            }

            $collection = $collectionFactory->create();

            $idField = 'post_id';
            if (method_exists($collection, 'getResource')) {
                $resource = $collection->getResource();
                if ($resource !== null && method_exists($resource, 'getIdFieldName')) {
                    $field = (string) $resource->getIdFieldName();
                    if ($field !== '') {
                        $idField = $field;
                    }
                }
            }

            $collection->addFieldToFilter($idField, $postId);
            $collection->setPageSize(1);

            foreach ($collection as $post) {
                if (method_exists($post, 'getData') && (int) $post->getData($idField) === $postId) {
                    return $post;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Panth SEO BlogDetector: current post load failed',
                ['class' => $modelClass, 'error' => $e->getMessage()]
            );
        }

        return null;
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

        if ($identifier === '' && method_exists($post, 'getData')) {
            foreach (['identifier', 'url_key'] as $key) {
                $value = $post->getData($key);
                if (is_scalar($value)) {
                    $identifier = trim((string) $value);
                    if ($identifier !== '') {
                        break;
                    }
                }
            }
        }

        if ($identifier !== '') {
            return $baseUrl . 'blog/' . ltrim($identifier, '/');
        }

        return '';
    }

    private function resolvePostTitle(object $post): string
    {
        foreach (['getTitle', 'getName'] as $method) {
            if (method_exists($post, $method)) {
                $title = trim((string) $post->{$method}());
                if ($title !== '') {
                    return $title;
                }
            }
        }

        if (method_exists($post, 'getData')) {
            foreach (['title', 'name'] as $key) {
                $value = $post->getData($key);
                if (is_scalar($value)) {
                    $title = trim((string) $value);
                    if ($title !== '') {
                        return $title;
                    }
                }
            }
        }

        return '';
    }
}
