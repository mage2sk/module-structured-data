<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Observer;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Panth\StructuredData\Observer\RegisterCmsPage;
use PHPUnit\Framework\TestCase;

class RegisterCmsPageTest extends TestCase
{
    private function observer(mixed $page): Observer
    {
        return new Observer(['event' => new Event(['page' => $page])]);
    }

    private function page(?int $id): PageInterface
    {
        $page = $this->createStub(PageInterface::class);
        $page->method('getId')->willReturn($id);

        return $page;
    }

    public function testRegistersPageWhenRegistryIsEmpty(): void
    {
        $page = $this->page(5);
        $registry = new Registry();

        (new RegisterCmsPage($registry))->execute($this->observer($page));

        $this->assertSame($page, $registry->registry(RegisterCmsPage::REGISTRY_KEY));
    }

    public function testExistingRegistrationIsKept(): void
    {
        $existing = $this->page(1);
        $registry = new Registry();
        $registry->register(RegisterCmsPage::REGISTRY_KEY, $existing);

        (new RegisterCmsPage($registry))->execute($this->observer($this->page(2)));

        $this->assertSame($existing, $registry->registry(RegisterCmsPage::REGISTRY_KEY));
    }

    public function testInvalidPagesAreIgnored(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->never())->method('register');
        $observer = new RegisterCmsPage($registry);

        $observer->execute($this->observer(null));
        $observer->execute($this->observer(new DataObject(['id' => 3])));
        $observer->execute($this->observer($this->page(null)));
    }
}
