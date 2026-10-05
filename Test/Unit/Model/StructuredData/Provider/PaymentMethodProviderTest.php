<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\PaymentMethodProvider;
use PHPUnit\Framework\TestCase;

class PaymentMethodProviderTest extends TestCase
{
    private function provider(string $methods): PaymentMethodProvider
    {
        $product = $this->createStub(Product::class);
        $product->method('getProductUrl')->willReturn('https://example.com/bag.html');

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnMap([
            ['current_product', $product],
        ]);

        $config = $this->createStub(Config::class);
        $config->method('getAcceptedPaymentMethods')->willReturn($methods);

        return new PaymentMethodProvider(
            $registry,
            $this->createStub(RequestInterface::class),
            $this->createStub(StoreManagerInterface::class),
            $config
        );
    }

    public function testMethodsAreAttachedToTheProductOffer(): void
    {
        $node = $this->provider("PayPal\nBank Transfer")->getJsonLd();

        $this->assertSame('Product', $node['@type']);
        $this->assertSame('https://example.com/bag.html#product', $node['@id']);
        $this->assertArrayNotHasKey('@type', $node['offers']);
        $this->assertSame(
            [
                'http://purl.org/goodrelations/v1#PayPal',
                'http://purl.org/goodrelations/v1#ByBankTransferInAdvance',
            ],
            $node['offers']['acceptedPaymentMethod']
        );
    }

    public function testCardsAreNotMappedToBankTransfer(): void
    {
        $node = $this->provider("Credit Card\nDebit Card\ncredit card\nVisa")->getJsonLd();

        $this->assertSame(
            [
                ['@type' => 'CreditCard', 'name' => 'Credit Card'],
                ['@type' => 'PaymentCard', 'name' => 'Debit Card'],
                'http://purl.org/goodrelations/v1#VISA',
            ],
            $node['offers']['acceptedPaymentMethod']
        );
    }

    public function testUnknownMethodsGiveNoNode(): void
    {
        $this->assertSame([], $this->provider("Crypto\nIOU")->getJsonLd());
    }
}
