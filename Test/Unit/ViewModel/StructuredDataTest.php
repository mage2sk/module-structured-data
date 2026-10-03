<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\ViewModel;

use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Composite;
use Panth\StructuredData\ViewModel\StructuredData;
use PHPUnit\Framework\TestCase;

class StructuredDataTest extends TestCase
{
    private function config(?bool $enabled): Config
    {
        $config = $this->createStub(Config::class);
        if ($enabled === null) {
            $config->method('isEnabled')->willThrowException(new \RuntimeException('config'));
        } else {
            $config->method('isEnabled')->willReturn($enabled);
        }

        return $config;
    }

    public function testIsEnabledReflectsConfigAndFailsClosed(): void
    {
        $composite = $this->createStub(Composite::class);

        $this->assertTrue((new StructuredData($composite, $this->config(true)))->isEnabled());
        $this->assertFalse((new StructuredData($composite, $this->config(false)))->isEnabled());
        $this->assertFalse((new StructuredData($composite, $this->config(null)))->isEnabled());
    }

    public function testJsonIsBuiltOnlyWhenEnabled(): void
    {
        $composite = $this->createMock(Composite::class);
        $composite->expects($this->once())->method('build')->willReturn('{"@type":"WebSite"}');

        $this->assertSame('', (new StructuredData($composite, $this->config(false)))->getJson());
        $this->assertSame('{"@type":"WebSite"}', (new StructuredData($composite, $this->config(true)))->getJson());
    }

    public function testBuildFailureGivesEmptyJson(): void
    {
        $composite = $this->createStub(Composite::class);
        $composite->method('build')->willThrowException(new \RuntimeException('build'));

        $this->assertSame('', (new StructuredData($composite, $this->config(true)))->getJson());
    }
}
