<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Panth\StructuredData\Model\StructuredData\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorCoverageTest extends TestCase
{
    public function testGraphNodesAreValidatedIndividually(): void
    {
        $errors = (new Validator())->validate([
            '@context' => 'https://schema.org',
            '@graph' => [
                'scalar',
                ['name' => 'No type'],
                ['@type' => 'Product', 'name' => ''],
                ['@type' => ['Offer', 'Thing'], 'price' => null, 'priceCurrency' => 'USD'],
                ['@type' => 'WebSite', 'name' => 'Shop', 'url' => 'not-a-url', 'image' => 'also bad'],
                ['@type' => 'UnknownType'],
            ],
        ]);

        $this->assertSame([
            'Node #0 is not an object',
            'Node #1 missing @type',
            'Product node is missing required property "name"',
            'Offer node is missing required property "price"',
            'WebSite node has invalid url "not-a-url"',
            'WebSite node has invalid image "also bad"',
        ], $errors);
    }

    public function testSingleValidNodeHasNoErrors(): void
    {
        $errors = (new Validator())->validate([
            '@type' => 'Organization',
            'name' => 'Acme',
            'url' => 'https://example.com/',
            'image' => 'https://example.com/logo.png',
        ]);

        $this->assertSame([], $errors);
    }

    public function testReviewSatisfiesSoftwareApplicationRatingRule(): void
    {
        $errors = (new Validator())->validate([
            '@type' => 'SoftwareApplication',
            'name' => 'App',
            'offers' => ['price' => '0'],
            'review' => [['@type' => 'Review']],
        ]);

        $this->assertSame([], $errors);
    }

    public function testEmptyTypeArrayIsTreatedAsUnknown(): void
    {
        $this->assertSame([], (new Validator())->validate(['@type' => []]));
    }
}
