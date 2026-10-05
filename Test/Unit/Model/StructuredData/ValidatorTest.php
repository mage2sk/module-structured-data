<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Panth\StructuredData\Model\StructuredData\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testSoftwareApplicationNeedsOffersAndRating(): void
    {
        $errors = (new Validator())->validate(['@type' => 'SoftwareApplication', 'name' => 'App']);

        $this->assertContains('SoftwareApplication node is missing required property "offers"', $errors);
        $this->assertContains('SoftwareApplication node needs "aggregateRating" or "review"', $errors);
    }

    public function testCompleteSoftwareApplicationIsValid(): void
    {
        $errors = (new Validator())->validate([
            '@type' => 'SoftwareApplication',
            'name' => 'App',
            'offers' => ['@type' => 'Offer', 'price' => '0.00', 'priceCurrency' => 'USD'],
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => '4.50', 'reviewCount' => 2],
        ]);

        $this->assertSame([], $errors);
    }
}
