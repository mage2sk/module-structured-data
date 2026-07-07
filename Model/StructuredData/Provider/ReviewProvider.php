<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Registry;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class ReviewProvider extends AbstractProvider
{
    private const MAX_REVIEWS = 5;

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleManager $moduleManager
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'review';
    }

    public function isApplicable(): bool
    {
        if ($this->isTestimonialsRoute()) {
            return false;
        }
        if ($this->getCurrentProduct() === null) {
            return false;
        }
        return (bool) $this->scopeConfig->isSetFlag('catalog/review/active', ScopeInterface::SCOPE_STORE);
    }

    private function isTestimonialsRoute(): bool
    {
        if (!$this->moduleManager->isEnabled('Panth_Testimonials')) {
            return false;
        }
        try {
            $routeName = (string) $this->request->getRouteName();
        } catch (\Throwable) {
            return false;
        }
        return $routeName === 'testimonials';
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $collection = $this->reviewCollectionFactory->create()
                ->addStoreFilter($storeId)
                ->addStatusFilter(Review::STATUS_APPROVED)
                ->addEntityFilter('product', (int) $product->getId())
                ->setDateOrder()
                ->addRateVotes();
            $collection->setPageSize(self::MAX_REVIEWS);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($collection as $review) {
            $title = trim((string) $review->getTitle());
            $detail = trim((string) $review->getDetail());
            $nickname = trim((string) $review->getNickname());
            if ($title === '' && $detail === '') {
                continue;
            }
            $ratingValue = $this->resolveReviewRating($review);
            $datePublished = $this->formatIso8601((string) ($review->getCreatedAt() ?? ''));
            $node = [
                '@type' => 'Review',
                'author' => [
                    '@type' => 'Person',
                    'name' => $nickname !== '' ? $nickname : 'Customer',
                ],
                'datePublished' => $datePublished !== '' ? $datePublished : (string) $review->getCreatedAt(),
                'reviewBody' => $detail !== '' ? $detail : $title,
                'name' => $title !== '' ? $title : mb_substr($detail, 0, 80),
                'itemReviewed' => ['@id' => (string) $product->getProductUrl() . '#product'],
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => $ratingValue,
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
            ];
            $out[] = $node;
        }
        return $out;
    }

    private function formatIso8601(string $datetime): string
    {
        if ($datetime === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($datetime))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveReviewRating(Review $review): string
    {
        try {
            $votes = $review->getRatingVotes();
            if ($votes !== null && count($votes) > 0) {
                $sum = 0.0;
                $count = 0;
                foreach ($votes as $vote) {
                    $percent = (float) $vote->getPercent();
                    if ($percent > 0) {
                        $sum += $percent;
                        $count++;
                    }
                }
                if ($count > 0) {
                    $avgPercent = $sum / $count;

                    $starRating = round(($avgPercent / 100.0) * 5.0, 1);
                    $starRating = max(1.0, min(5.0, $starRating));
                    return number_format($starRating, 1, '.', '');
                }
            }
        } catch (\Throwable) {
        }

        return '5';
    }
}
