<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData;

use Panth\StructuredData\Api\StructuredDataProviderInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Composite;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CompositeTest extends TestCase
{
    private function composite(bool $enabled, array $node): Composite
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isStructuredDataEnabled')->willReturn(true);

        $provider = $this->createStub(StructuredDataProviderInterface::class);
        $provider->method('isApplicable')->willReturn(true);
        $provider->method('getCode')->willReturn('organization');
        $provider->method('getJsonLd')->willReturn($node);

        return new Composite(['organization' => $provider], $config, $this->createStub(LoggerInterface::class));
    }

    public function testIdentity(): void
    {
        $composite = $this->composite(true, []);

        $this->assertSame('composite', $composite->getCode());
        $this->assertTrue($composite->isApplicable());
    }

    public function testJsonLdIsTheDecodedDocument(): void
    {
        $composite = $this->composite(true, ['@type' => 'Organization', 'name' => 'Acme']);

        $this->assertSame(
            ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Acme'],
            $composite->getJsonLd()
        );
    }

    public function testDisabledModuleGivesEmptyArray(): void
    {
        $this->assertSame([], $this->composite(false, ['@type' => 'Organization'])->getJsonLd());
    }
}
