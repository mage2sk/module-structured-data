<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Psr\Log\LoggerInterface;

class CustomPropertiesProvider extends AbstractProvider
{
    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'custom_properties';
    }

    public function isApplicable(): bool
    {
        return $this->getCurrentProduct() !== null;
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }

        $raw = trim((string) ($this->config->getValue(Config::XML_SD_CUSTOM_PROPERTIES) ?? ''));
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning(
                sprintf(
                    '[Panth_StructuredData] Invalid JSON in custom_properties config: %s',
                    $e->getMessage()
                )
            );
            return [];
        }

        if (!is_array($decoded) || $decoded === []) {
            return [];
        }

        $url = (string) $product->getProductUrl();

        return array_merge(
            [
                '@type' => 'Product',
                '@id'   => $url . '#product',
            ],
            $decoded
        );
    }
}
