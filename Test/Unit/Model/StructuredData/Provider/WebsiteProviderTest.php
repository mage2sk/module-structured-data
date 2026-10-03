<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Panth\StructuredData\Model\StructuredData\Provider\WebsiteProvider;

class WebsiteProviderTest extends AbstractProviderTestCase
{
    public function testBuildsWebsiteNodeWithSearchAction(): void
    {
        $provider = new WebsiteProvider(
            $this->registry(),
            $this->request(),
            $this->storeManager($this->store(1, 'https://shop.test', 'Shop')),
            $this->config()
        );

        $node = $provider->getJsonLd();

        $this->assertSame('website', $provider->getCode());
        $this->assertSame('WebSite', $node['@type']);
        $this->assertSame('https://shop.test/#website', $node['@id']);
        $this->assertSame('Shop', $node['name']);
        $this->assertSame('https://shop.test/', $node['url']);
        $this->assertSame(['@id' => 'https://shop.test/#organization'], $node['publisher']);
        $this->assertSame(
            'https://shop.test/catalogsearch/result/?q={search_term_string}',
            $node['potentialAction']['target']['urlTemplate']
        );
        $this->assertSame('required name=search_term_string', $node['potentialAction']['query-input']);
    }

    public function testEmptyStoreNameFallsBackToStore(): void
    {
        $provider = new WebsiteProvider(
            $this->registry(),
            $this->request(),
            $this->storeManager($this->store(1, self::BASE, '')),
            $this->config()
        );

        $this->assertSame('Store', $provider->getJsonLd()['name']);
    }

    public function testStoreFailureGivesNoNode(): void
    {
        $provider = new WebsiteProvider(
            $this->registry(),
            $this->request(),
            $this->failingStoreManager(),
            $this->config()
        );

        $this->assertSame([], $provider->getJsonLd());
    }
}
