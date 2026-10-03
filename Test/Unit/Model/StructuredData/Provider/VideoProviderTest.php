<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryExtensionInterface;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Framework\Api\Data\VideoContentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\VideoProvider;

class VideoProviderTest extends AbstractProviderTestCase
{
    private function provider(array $registry, ?StoreManagerInterface $storeManager = null): VideoProvider
    {
        return new VideoProvider(
            $this->registry($registry),
            $this->request(),
            $storeManager ?? $this->storeManager(),
            $this->config()
        );
    }

    private function entry(
        ?VideoContentInterface $video,
        string $file = '/a/b/clip.jpg',
        ?string $label = null
    ): ProductAttributeMediaGalleryEntryInterface {
        $ext = $this->createStub(ProductAttributeMediaGalleryEntryExtensionInterface::class);
        $ext->method('getVideoContent')->willReturn($video);
        $entry = $this->createStub(ProductAttributeMediaGalleryEntryInterface::class);
        $entry->method('getExtensionAttributes')->willReturn($ext);
        $entry->method('getFile')->willReturn($file);
        $entry->method('getLabel')->willReturn($label);

        return $entry;
    }

    private function video(string $url, ?string $title = null, ?string $description = null): VideoContentInterface
    {
        $video = $this->createStub(VideoContentInterface::class);
        $video->method('getVideoUrl')->willReturn($url);
        $video->method('getVideoTitle')->willReturn($title);
        $video->method('getVideoDescription')->willReturn($description);

        return $video;
    }

    private function productWith(array $entries): \Magento\Catalog\Model\Product
    {
        return $this->product([
            'getMediaGalleryEntries' => $entries,
            'getProductUrl' => 'https://example.com/cam.html',
            'getName' => 'Camera',
            'getCreatedAt' => '2024-01-02T03:04:05+00:00',
        ]);
    }

    public function testApplicability(): void
    {
        $this->assertSame('video', $this->provider([])->getCode());
        $this->assertFalse($this->provider([])->isApplicable());
        $this->assertTrue($this->provider(['current_product' => $this->productWith([])])->isApplicable());
        $this->assertSame([], $this->provider([])->getJsonLd());
    }

    public function testNoGalleryEntriesGivesNoNodes(): void
    {
        $this->assertSame([], $this->provider(['current_product' => $this->productWith([])])->getJsonLd());
    }

    public function testBuildsVideoNodesAndSkipsImagesAndEmptyUrls(): void
    {
        $product = $this->productWith([
            $this->entry(null),
            $this->entry($this->video('')),
            $this->entry($this->video('https://youtu.be/x', 'Intro', 'Watch it')),
            $this->entry($this->video('https://youtu.be/y'), '', 'Gallery label'),
        ]);

        $nodes = $this->provider(['current_product' => $product])->getJsonLd();

        $this->assertCount(2, $nodes);
        $this->assertSame('VideoObject', $nodes[0]['@type']);
        $this->assertSame('https://example.com/cam.html#video-1', $nodes[0]['@id']);
        $this->assertSame('Intro', $nodes[0]['name']);
        $this->assertSame('Watch it', $nodes[0]['description']);
        $this->assertSame('https://example.com/media/catalog/product/a/b/clip.jpg', $nodes[0]['thumbnailUrl']);
        $this->assertSame('2024-01-02T03:04:05+00:00', $nodes[0]['uploadDate']);
        $this->assertSame('https://youtu.be/x', $nodes[0]['contentUrl']);
        $this->assertSame('https://youtu.be/x', $nodes[0]['embedUrl']);

        $this->assertSame('https://example.com/cam.html#video-2', $nodes[1]['@id']);
        $this->assertSame('Gallery label', $nodes[1]['name']);
        $this->assertSame('Gallery label', $nodes[1]['description']);
        $this->assertSame('https://youtu.be/y', $nodes[1]['thumbnailUrl']);
    }

    public function testTitleFallsBackToProductNameAndMediaUrlToBaseOnStoreFailure(): void
    {
        $product = $this->productWith([$this->entry($this->video('https://v.test/1'), 'f.jpg')]);

        $nodes = $this->provider(['current_product' => $product], $this->failingStoreManager())->getJsonLd();

        $this->assertSame('Camera', $nodes[0]['name']);
        $this->assertSame('/media/catalog/product/f.jpg', $nodes[0]['thumbnailUrl']);
    }
}
