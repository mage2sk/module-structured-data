<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Review\Model\Review;
use Panth\StructuredData\Model\StructuredData\Provider\ReviewProvider;

class ReviewProviderTest extends AbstractProviderTestCase
{
    private function provider(
        array $registry,
        ?CollectionFactory $factory = null,
        array $flags = ['catalog/review/active' => true],
        bool $testimonialsEnabled = false,
        ?RequestInterface $request = null
    ): ReviewProvider {
        $moduleManager = $this->createStub(ModuleManager::class);
        $moduleManager->method('isEnabled')->willReturnCallback(
            static fn(string $name) => $name === 'Panth_Testimonials' && $testimonialsEnabled
        );

        return new ReviewProvider(
            $this->registry($registry),
            $request ?? $this->request(),
            $this->storeManager(),
            $this->config(),
            $factory ?? $this->createStub(CollectionFactory::class),
            $this->scopeConfig([], $flags),
            $moduleManager
        );
    }

    private function review(array $data): Review
    {
        $review = (new \ReflectionClass(Review::class))->newInstanceWithoutConstructor();
        $review->setData($data);
        $review->setId($data['review_id'] ?? null);

        return $review;
    }

    private function factory(array $reviews): CollectionFactory
    {
        $collection = $this->createStub(Collection::class);
        foreach (['addStoreFilter', 'addStatusFilter', 'addEntityFilter', 'setDateOrder', 'addRateVotes'] as $m) {
            $collection->method($m)->willReturnSelf();
        }
        $collection->method('getIterator')->willReturn(new \ArrayIterator($reviews));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    private function currentProduct(): array
    {
        return ['current_product' => $this->product(['getId' => 7, 'getProductUrl' => 'https://example.com/p.html'])];
    }

    private function routeRequest(string $route): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getRouteName')->willReturn($route);

        return $request;
    }

    public function testApplicability(): void
    {
        $this->assertSame('review', $this->provider([])->getCode());
        $this->assertFalse($this->provider([])->isApplicable());
        $this->assertFalse($this->provider($this->currentProduct(), null, [])->isApplicable());
        $this->assertTrue($this->provider($this->currentProduct())->isApplicable());
    }

    public function testTestimonialsRouteSuppressesReviews(): void
    {
        $testimonials = $this->routeRequest('testimonials');
        $catalog = $this->routeRequest('catalog');

        $this->assertFalse(
            $this->provider($this->currentProduct(), null, ['catalog/review/active' => true], true, $testimonials)
                ->isApplicable()
        );
        $this->assertTrue(
            $this->provider($this->currentProduct(), null, ['catalog/review/active' => true], true, $catalog)
                ->isApplicable()
        );
        $this->assertTrue(
            $this->provider($this->currentProduct(), null, ['catalog/review/active' => true], false, $testimonials)
                ->isApplicable()
        );
    }

    public function testRouteLookupFailureDoesNotSuppress(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getRouteName')->willThrowException(new \RuntimeException('boom'));

        $this->assertTrue(
            $this->provider($this->currentProduct(), null, ['catalog/review/active' => true], true, $request)
                ->isApplicable()
        );
    }

    public function testNoProductGivesNoNodes(): void
    {
        $this->assertSame([], $this->provider([])->getJsonLd());
    }

    public function testCollectionFailureGivesNoNodes(): void
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willThrowException(new \RuntimeException('db down'));

        $this->assertSame([], $this->provider($this->currentProduct(), $factory)->getJsonLd());
    }

    public function testBuildsReviewNodesWithAveragedRating(): void
    {
        $votes = [
            new DataObject(['percent' => 80]),
            new DataObject(['percent' => 0]),
            new DataObject(['percent' => 100]),
        ];
        $reviews = [
            $this->review(['review_id' => 3, 'title' => '', 'detail' => '', 'nickname' => 'Skip']),
            $this->review([
                'review_id' => 4,
                'title' => ' Great ',
                'detail' => ' Works well ',
                'nickname' => ' Ann ',
                'created_at' => '2024-05-06T07:08:09+00:00',
                'rating_votes' => $votes,
            ]),
        ];

        $nodes = $this->provider($this->currentProduct(), $this->factory($reviews))->getJsonLd();

        $this->assertCount(1, $nodes);
        $node = $nodes[0];
        $this->assertSame('Review', $node['@type']);
        $this->assertSame('https://example.com/p.html#review-4', $node['@id']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Ann'], $node['author']);
        $this->assertSame('2024-05-06T07:08:09+00:00', $node['datePublished']);
        $this->assertSame('Works well', $node['reviewBody']);
        $this->assertSame('Great', $node['name']);
        $this->assertSame(['@id' => 'https://example.com/p.html#product'], $node['itemReviewed']);
        $this->assertSame('4.5', $node['reviewRating']['ratingValue']);
        $this->assertSame('5', $node['reviewRating']['bestRating']);
        $this->assertSame('1', $node['reviewRating']['worstRating']);
    }

    public function testFallbacksForMissingFieldsAndLowRating(): void
    {
        $longDetail = str_repeat('a', 100);
        $reviews = [
            $this->review([
                'review_id' => 5,
                'title' => '',
                'detail' => $longDetail,
                'nickname' => '',
                'created_at' => 'not a date',
                'rating_votes' => [new DataObject(['percent' => 5])],
            ]),
            $this->review([
                'review_id' => 6,
                'title' => 'Only title',
                'detail' => '',
                'rating_votes' => [],
            ]),
        ];

        $nodes = $this->provider($this->currentProduct(), $this->factory($reviews))->getJsonLd();

        $this->assertSame('Customer', $nodes[0]['author']['name']);
        $this->assertSame('not a date', $nodes[0]['datePublished']);
        $this->assertSame(str_repeat('a', 80), $nodes[0]['name']);
        $this->assertSame($longDetail, $nodes[0]['reviewBody']);
        $this->assertSame('1.0', $nodes[0]['reviewRating']['ratingValue']);

        $this->assertSame('Only title', $nodes[1]['reviewBody']);
        $this->assertSame('', $nodes[1]['datePublished']);
        $this->assertArrayNotHasKey('reviewRating', $nodes[1]);
    }
}
