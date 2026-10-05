<?php
declare(strict_types=1);

namespace Panth\StructuredData\Observer;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;

class RegisterCmsPage implements ObserverInterface
{
    public const REGISTRY_KEY = 'cms_page';

    public function __construct(
        private readonly Registry $registry
    ) {
    }

    public function execute(Observer $observer): void
    {
        $page = $observer->getEvent()->getData('page');
        if (!$page instanceof PageInterface || !$page->getId()) {
            return;
        }

        if ($this->registry->registry(self::REGISTRY_KEY) !== null) {
            return;
        }

        $this->registry->register(self::REGISTRY_KEY, $page);
    }
}
