<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\Blog\BlogDetector;

class BlogPostProvider extends AbstractProvider
{
    private const POST_VIEW_ACTIONS = ['mpblog_post_view'];

    private const REGISTRY_KEYS = ['current_blog_post', 'mp_current_post', 'current_post'];

    private ?object $currentPost = null;

    private bool $postResolved = false;

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly BlogDetector $blogDetector,
        private readonly UrlInterface $url
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'article';
    }

    public function isApplicable(): bool
    {
        return $this->resolvePost() !== null;
    }

    public function getJsonLd(): array
    {
        $post = $this->resolvePost();
        if ($post === null) {
            return [];
        }

        $headline = $this->postValue($post, ['title', 'name']);
        if ($headline === '') {
            return [];
        }

        $url = $this->resolvePostUrl($post);
        $base = $this->getBaseUrl();
        $body = trim(strip_tags($this->postValue($post, ['content', 'post_content', 'short_content', 'short_description'])));

        $description = $this->postValue($post, ['meta_description']);
        if ($description === '') {
            $description = mb_substr($body, 0, 300);
        }

        $node = [
            '@type' => 'BlogPosting',
            '@id' => $url . '#article',
            'headline' => $headline,
            'mainEntityOfPage' => $url,
            'url' => $url,
            'author' => ['@id' => $base . '#organization'],
            'publisher' => ['@id' => $base . '#organization'],
        ];

        if ($description !== '') {
            $node['description'] = $description;
        }
        if ($body !== '') {
            $node['articleBody'] = mb_substr($body, 0, 5000);
        }

        $image = $this->resolveImageUrl($post);
        if ($image !== '') {
            $node['image'] = $image;
        }

        $datePublished = $this->formatIso8601(
            $this->postValue($post, ['publish_time', 'publish_date', 'created_at', 'creation_time'])
        );
        if ($datePublished !== '') {
            $node['datePublished'] = $datePublished;
        }
        $dateModified = $this->formatIso8601($this->postValue($post, ['update_time', 'updated_at']));
        if ($dateModified !== '') {
            $node['dateModified'] = $dateModified;
        }

        return $node;
    }

    private function resolvePost(): ?object
    {
        if ($this->postResolved) {
            return $this->currentPost;
        }
        $this->postResolved = true;

        $action = method_exists($this->request, 'getFullActionName')
            ? strtolower((string) $this->request->getFullActionName())
            : '';
        if (!in_array($action, self::POST_VIEW_ACTIONS, true)) {
            return null;
        }

        foreach (self::REGISTRY_KEYS as $key) {
            $candidate = $this->registry->registry($key);
            if (is_object($candidate) && method_exists($candidate, 'getData')) {
                $this->currentPost = $candidate;
                return $this->currentPost;
            }
        }

        $postId = (int) ($this->request->getParam('id') ?: $this->request->getParam('post_id'));
        if ($postId > 0) {
            $this->currentPost = $this->blogDetector->getPostById($action, $postId);
        }

        return $this->currentPost;
    }

    private function postValue(object $post, array $keys): string
    {
        if (!method_exists($post, 'getData')) {
            return '';
        }

        foreach ($keys as $key) {
            $value = $post->getData($key);
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function resolvePostUrl(object $post): string
    {
        foreach (['getPostUrl', 'getUrl'] as $method) {
            if (method_exists($post, $method)) {
                try {
                    $url = trim((string) $post->{$method}());
                    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                        return $url;
                    }
                } catch (\Throwable) {
                }
            }
        }

        $current = (string) $this->url->getCurrentUrl();
        $pos = strpos($current, '?');
        return $pos === false ? $current : substr($current, 0, $pos);
    }

    private function resolveImageUrl(object $post): string
    {
        $image = $this->postValue($post, ['featured_img', 'featured_image', 'image']);
        if ($image === '') {
            return '';
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        try {
            $mediaBase = (string) $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        } catch (\Throwable) {
            return '';
        }

        return rtrim($mediaBase, '/') . '/' . ltrim($image, '/');
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
}
