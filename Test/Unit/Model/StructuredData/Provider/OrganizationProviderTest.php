<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\OrganizationProvider;

class OrganizationProviderTest extends AbstractProviderTestCase
{
    private function provider(array $values, ?StoreManagerInterface $storeManager = null): OrganizationProvider
    {
        return new OrganizationProvider(
            $this->registry(),
            $this->request(),
            $storeManager ?? $this->storeManager($this->store(1, 'https://shop.test', 'Fallback Name')),
            $this->config($values),
            $this->scopeConfig($values)
        );
    }

    public function testMinimalNodeUsesStoreNameAndUrl(): void
    {
        $provider = $this->provider([]);

        $this->assertSame('organization', $provider->getCode());
        $this->assertSame([
            '@type' => 'Organization',
            '@id' => 'https://shop.test/#organization',
            'name' => 'Fallback Name',
            'url' => 'https://shop.test/',
        ], $provider->getJsonLd());
    }

    public function testEmptyNamesFallBackToStore(): void
    {
        $provider = $this->provider([], $this->storeManager($this->store(1, 'https://shop.test/', '')));

        $this->assertSame('Store', $provider->getJsonLd()['name']);
    }

    public function testStoreFailureGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([], $this->failingStoreManager())->getJsonLd());
    }

    public function testFullNode(): void
    {
        $node = $this->provider([
            'general/store_information/name' => 'Acme Ltd',
            OrganizationProvider::XML_LEGAL_NAME => 'Acme Limited',
            OrganizationProvider::XML_LOGO => 'https://shop.test/logo.png',
            OrganizationProvider::XML_PHONE => '+44 1',
            OrganizationProvider::XML_EMAIL => ' mailto:help@shop.test ',
            OrganizationProvider::XML_FOUNDER_ID => '/about#founder',
            OrganizationProvider::XML_STREET => '1 Road',
            OrganizationProvider::XML_LOCALITY => 'Town',
            OrganizationProvider::XML_REGION => 'County',
            OrganizationProvider::XML_POSTCODE => 'AB1',
            OrganizationProvider::XML_COUNTRY => 'GB',
            OrganizationProvider::XML_SAME_AS => "https://fb.test/acme\nnot a url, https://x.test/acme\r\nhttps://fb.test/acme",
            Config::XML_SOCIAL_PROFILE_YOUTUBE => 'https://yt.test/acme',
            Config::XML_SOCIAL_PROFILE_TWITTER => 'https://x.test/acme',
        ])->getJsonLd();

        $this->assertSame('Acme Ltd', $node['name']);
        $this->assertSame('Acme Limited', $node['legalName']);
        $this->assertSame(['@type' => 'ImageObject', 'url' => 'https://shop.test/logo.png'], $node['logo']);
        $this->assertSame([[
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'telephone' => '+44 1',
            'email' => 'help@shop.test',
        ]], $node['contactPoint']);
        $this->assertSame(['@id' => 'https://shop.test/about#founder'], $node['founder']);
        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '1 Road',
            'addressLocality' => 'Town',
            'addressRegion' => 'County',
            'postalCode' => 'AB1',
            'addressCountry' => 'GB',
        ], $node['address']);
        $this->assertSame(
            ['https://fb.test/acme', 'https://x.test/acme', 'https://yt.test/acme'],
            $node['sameAs']
        );
    }

    public function testStorePhoneFallbackAndAbsoluteFounderAndEmailOnlyContact(): void
    {
        $node = $this->provider([
            'general/store_information/phone' => '555',
            OrganizationProvider::XML_FOUNDER_ID => 'https://people.test/jane',
            OrganizationProvider::XML_REGION => 'Only region',
        ])->getJsonLd();

        $this->assertSame('555', $node['contactPoint'][0]['telephone']);
        $this->assertArrayNotHasKey('email', $node['contactPoint'][0]);
        $this->assertSame(['@id' => 'https://people.test/jane'], $node['founder']);
        $this->assertArrayNotHasKey('address', $node);
        $this->assertArrayNotHasKey('sameAs', $node);
    }

    public function testEmailOnlyContactPoint(): void
    {
        $node = $this->provider([
            OrganizationProvider::XML_EMAIL => 'info@shop.test',
            OrganizationProvider::XML_COUNTRY => 'US',
        ])->getJsonLd();

        $this->assertSame(
            [['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'info@shop.test']],
            $node['contactPoint']
        );
        $this->assertSame(['@type' => 'PostalAddress', 'addressCountry' => 'US'], $node['address']);
    }
}
