<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Panth\StructuredData\Api\StructuredDataProviderInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Aggregator;
use Panth\StructuredData\Model\StructuredData\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AggregatorCoverageTest extends TestCase
{
    private function provider(string $code, array $node, bool $applicable = true): StructuredDataProviderInterface
    {
        $provider = $this->createStub(StructuredDataProviderInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('isApplicable')->willReturn($applicable);
        $provider->method('getJsonLd')->willReturn($node);

        return $provider;
    }

    private function config(bool $enabled = true, array $disabledCodes = [], bool $debug = false): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isStructuredDataEnabled')->willReturnCallback(
            static fn(string $code) => !in_array($code, $disabledCodes, true)
        );
        $config->method('isDebug')->willReturn($debug);

        return $config;
    }

    public function testDisabledModuleBuildsNothing(): void
    {
        $aggregator = new Aggregator(
            ['org' => $this->provider('organization', ['@type' => 'Organization', 'name' => 'A'])],
            $this->config(false),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame('', $aggregator->build());
    }

    public function testNonProvidersAndSkippedProvidersAreIgnored(): void
    {
        $aggregator = new Aggregator(
            [
                'junk' => new \stdClass(),
                'na' => $this->provider('website', ['@type' => 'WebSite', 'name' => 'x'], false),
                'off' => $this->provider('faq', ['@type' => 'FAQPage', 'name' => 'x']),
                'empty' => $this->provider('video', []),
                'typeless' => $this->provider('brand', [['name' => 'no type'], 'scalar']),
            ],
            $this->config(true, ['faq']),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame('', $aggregator->build());
    }

    public function testSingleNodeIsInlinedWithContextAndEscaped(): void
    {
        $aggregator = new Aggregator(
            ['org' => $this->provider('organization', ['@type' => 'Organization', 'name' => '<b>A&B</b>'])],
            $this->config(),
            $this->createStub(LoggerInterface::class)
        );

        $json = $aggregator->build();

        $this->assertStringNotContainsString('<b>', $json);
        $this->assertStringContainsString('\u003Cb\u003EA\u0026B', $json);
        $this->assertSame(
            ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => '<b>A&B</b>'],
            json_decode($json, true)
        );
    }

    public function testProviderExceptionIsLoggedAndOthersStillRender(): void
    {
        $broken = $this->createStub(StructuredDataProviderInterface::class);
        $broken->method('isApplicable')->willReturn(true);
        $broken->method('getCode')->willReturn('review');
        $broken->method('getJsonLd')->willThrowException(new \RuntimeException('kaboom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('[Panth_StructuredData] provider "review" failed: kaboom', $this->arrayHasKey('exception'));

        $aggregator = new Aggregator(
            [
                'review' => $broken,
                'org' => $this->provider('organization', ['@type' => 'Organization', 'name' => 'A']),
            ],
            $this->config(),
            $logger
        );

        $this->assertSame('Organization', json_decode($aggregator->build(), true)['@type']);
    }

    public function testDebugModeLogsValidationIssues(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Organization node is missing required property "name"'));

        $aggregator = new Aggregator(
            ['org' => $this->provider('organization', ['@type' => 'Organization', 'url' => 'https://a.test/'])],
            $this->config(true, [], true),
            $logger,
            new Validator()
        );

        $this->assertNotSame('', $aggregator->build());
    }

    public function testDebugModeWithValidDocumentLogsNothing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $aggregator = new Aggregator(
            ['org' => $this->provider('organization', ['@type' => 'Organization', 'name' => 'A'])],
            $this->config(true, [], true),
            $logger,
            new Validator()
        );

        $this->assertNotSame('', $aggregator->build());
    }

    public function testAggregateOfferNeedsLowPriceAndCleansChildOffers(): void
    {
        $aggregator = new Aggregator(
            [
                'p' => $this->provider('product', [
                    '@type' => 'Product',
                    '@id' => 'https://a.test/p#product',
                    'name' => 'P',
                    'offers' => [
                        '@type' => 'AggregateOffer',
                        'lowPrice' => 5,
                        'offers' => [
                            ['@type' => 'Offer', 'price' => 5.5],
                            ['@type' => 'Offer', 'priceSpecification' => ['price' => '6.00']],
                            ['@type' => 'Offer', 'price' => 'n/a'],
                            'scalar',
                        ],
                    ],
                ]),
                'q' => $this->provider('product', [
                    '@type' => 'Product',
                    '@id' => 'https://a.test/q#product',
                    'name' => 'Q',
                    'offers' => [
                        '@type' => 'AggregateOffer',
                        'lowPrice' => '',
                        'offers' => [['@type' => 'Offer']],
                    ],
                ]),
                'r' => $this->provider('product', [
                    '@type' => 'Product',
                    '@id' => 'https://a.test/r#product',
                    'name' => 'R',
                    'offers' => ['@type' => 'AggregateOffer', 'lowPrice' => '1', 'offers' => [['price' => 'x']]],
                ]),
            ],
            $this->config(),
            $this->createStub(LoggerInterface::class)
        );

        $graph = json_decode($aggregator->build(), true)['@graph'];

        $this->assertCount(2, $graph[0]['offers']['offers']);
        $this->assertArrayNotHasKey('offers', $graph[1]);
        $this->assertArrayNotHasKey('offers', $graph[2]['offers']);
        $this->assertSame('1', $graph[2]['offers']['lowPrice']);
    }

    public function testVariantOffersAreCleanedAndEmptyOffersDropped(): void
    {
        $aggregator = new Aggregator(
            [
                'g' => $this->provider('product_group', [
                    '@type' => 'ProductGroup',
                    '@id' => 'https://a.test/g#product',
                    'name' => 'G',
                    'offers' => [],
                    'hasVariant' => [
                        '@type' => 'Product',
                        'name' => 'Single variant',
                        'offers' => ['@type' => 'Offer', 'price' => 'free'],
                    ],
                ]),
                'h' => $this->provider('product_group', [
                    '@type' => 'ProductGroup',
                    '@id' => 'https://a.test/h#product',
                    'name' => 'H',
                    'hasVariant' => [
                        ['@type' => 'Product', 'offers' => [['price' => '1'], ['price' => null]]],
                        ['@type' => 'Product'],
                    ],
                ]),
            ],
            $this->config(),
            $this->createStub(LoggerInterface::class)
        );

        $graph = json_decode($aggregator->build(), true)['@graph'];

        $this->assertArrayNotHasKey('offers', $graph[0]);
        $this->assertSame([['@type' => 'Product', 'name' => 'Single variant']], $graph[0]['hasVariant']);
        $this->assertSame([['price' => '1']], $graph[1]['hasVariant'][0]['offers']);
    }

    public function testInvalidUtf8IsSubstitutedRatherThanFailing(): void
    {
        $aggregator = new Aggregator(
            ['org' => $this->provider('organization', ['@type' => 'Organization', 'name' => "Bad \xB1 byte"])],
            $this->config(),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertStringStartsWith('Bad ', json_decode($aggregator->build(), true)['name']);
    }
}
