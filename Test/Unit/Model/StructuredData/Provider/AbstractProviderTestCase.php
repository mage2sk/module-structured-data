<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use PHPUnit\Framework\TestCase;

abstract class AbstractProviderTestCase extends TestCase
{
    protected const BASE = 'https://example.com/';

    protected function registry(array $entries = []): Registry
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn(string $key) => $entries[$key] ?? null
        );

        return $registry;
    }

    protected function store(
        int $id = 1,
        string $baseUrl = self::BASE,
        string $name = 'Example Store',
        string $currency = 'USD'
    ): Store {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn($type = 'link') => $type === 'media' ? $baseUrl . 'media/' : $baseUrl
        );
        $store->method('getName')->willReturn($name);
        $store->method('getCurrentCurrencyCode')->willReturn($currency);
        $store->method('getRootCategoryId')->willReturn(2);

        return $store;
    }

    protected function storeManager(?Store $store = null): StoreManagerInterface
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store ?? $this->store());

        return $storeManager;
    }

    protected function failingStoreManager(): StoreManagerInterface
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));

        return $storeManager;
    }

    protected function scopeConfig(array $values = [], array $flags = []): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path) => (bool) ($flags[$path] ?? false)
        );

        return $scopeConfig;
    }

    protected function config(array $values = [], array $flags = []): Config
    {
        return new Config($this->scopeConfig($values, $flags));
    }

    protected function request(): RequestInterface
    {
        return $this->createStub(RequestInterface::class);
    }

    protected function product(array $methods = [], array $data = []): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturnCallback(
            static fn($key = '', $index = null) => $key === '' ? $data : ($data[$key] ?? null)
        );
        $product->method('hasData')->willReturnCallback(
            static fn($key = '') => array_key_exists($key, $data)
        );
        if (!array_key_exists('__call', $methods)) {
            $product->method('__call')->willReturnCallback(
                static function (string $method) use ($data) {
                    $key = strtolower((string) preg_replace('/(.)([A-Z])/', '$1_$2', substr($method, 3)));
                    return match (substr($method, 0, 3)) {
                        'get' => $data[$key] ?? null,
                        'has' => array_key_exists($key, $data),
                        default => null,
                    };
                }
            );
        }
        foreach ($methods as $name => $value) {
            if ($value instanceof \Closure) {
                $product->method($name)->willReturnCallback($value);
            } else {
                $product->method($name)->willReturn($value);
            }
        }

        return $product;
    }
}
