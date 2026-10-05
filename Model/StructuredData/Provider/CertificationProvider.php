<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class CertificationProvider extends AbstractProvider
{
    private const XML_ENABLED   = 'panth_structured_data/structured_data/certification_enabled';
    private const XML_ATTRIBUTE = 'panth_structured_data/structured_data/certification_attribute';

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'certification_enabled';
    }

    public function isApplicable(): bool
    {
        if ($this->getCurrentProduct() === null) {
            return false;
        }

        return $this->isFeatureEnabled();
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }

        $attributeCode = $this->getCertificationAttribute();
        $raw = trim((string) ($product->getData($attributeCode) ?? ''));
        if ($raw === '') {
            return [];
        }

        $certifications = $this->parseCertifications($raw);
        if ($certifications === []) {
            return [];
        }

        $url = (string) $product->getProductUrl();

        return [
            '@type'            => 'Product',
            '@id'              => $url . '#product',
            'hasCertification' => count($certifications) === 1
                ? $certifications[0]
                : $certifications,
        ];
    }

    private function parseCertifications(string $raw): array
    {
        $lines = preg_split('/\r?\n/', $raw);
        if ($lines === false) {
            return [];
        }

        $certifications = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            $authority = $parts[0] ?? '';
            $name      = $parts[1] ?? '';
            $id        = $parts[2] ?? '';

            if ($authority === '' || $name === '') {
                continue;
            }

            $node = [
                '@type' => 'Certification',
                'certificationAuthority' => [
                    '@type' => 'Organization',
                    'name'  => $authority,
                ],
                'name' => $name,
            ];

            if ($id !== '') {
                $node['certificationIdentification'] = $id;
            }

            $certifications[] = $node;
        }

        return $certifications;
    }

    private function isFeatureEnabled(): bool
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = null;
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    private function getCertificationAttribute(): string
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = null;
        }

        $value = (string) ($this->scopeConfig->getValue(
            self::XML_ATTRIBUTE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '');

        return $value !== '' ? $value : 'certifications';
    }
}
