<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Block\Head;

use Magento\Framework\View\Element\Template\Context;
use Panth\StructuredData\Block\Head\StructuredData;
use Panth\StructuredData\ViewModel\StructuredData as StructuredDataViewModel;
use PHPUnit\Framework\TestCase;

class StructuredDataTest extends TestCase
{
    public function testDelegatesToViewModel(): void
    {
        $viewModel = $this->createStub(StructuredDataViewModel::class);
        $viewModel->method('getJson')->willReturn('{"@type":"Organization"}');
        $viewModel->method('isEnabled')->willReturn(true);

        $block = new StructuredData($this->createStub(Context::class), $viewModel);

        $this->assertSame('{"@type":"Organization"}', $block->getJson());
        $this->assertTrue($block->isEnabled());
    }

    public function testDisabledViewModel(): void
    {
        $viewModel = $this->createStub(StructuredDataViewModel::class);
        $viewModel->method('getJson')->willReturn('');
        $viewModel->method('isEnabled')->willReturn(false);

        $block = new StructuredData($this->createStub(Context::class), $viewModel);

        $this->assertSame('', $block->getJson());
        $this->assertFalse($block->isEnabled());
    }
}
