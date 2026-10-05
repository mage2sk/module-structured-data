<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Panth\StructuredData\Helper\Config;
use Panth\StructuredData\Model\StructuredData\Provider\PaymentMethodProvider;

class PaymentMethodProviderCoverageTest extends AbstractProviderTestCase
{
    private function provider(array $registry, ?string $methods): PaymentMethodProvider
    {
        return new PaymentMethodProvider(
            $this->registry($registry),
            $this->request(),
            $this->storeManager(),
            $this->config([Config::XML_SD_ACCEPTED_PAYMENT => $methods])
        );
    }

    private function withProduct(): array
    {
        return ['current_product' => $this->product(['getProductUrl' => 'https://example.com/p.html'])];
    }

    public function testApplicabilityNeedsProductAndConfiguredMethods(): void
    {
        $this->assertSame('paymentMethod', $this->provider([], 'Visa')->getCode());
        $this->assertFalse($this->provider([], 'Visa')->isApplicable());
        $this->assertFalse($this->provider($this->withProduct(), null)->isApplicable());
        $this->assertFalse($this->provider($this->withProduct(), " \n \r\n")->isApplicable());
        $this->assertTrue($this->provider($this->withProduct(), 'Visa')->isApplicable());
    }

    public function testDuplicatesAndAliasesAreCollapsed(): void
    {
        $node = $this->provider(
            $this->withProduct(),
            "VISA\nvisa\r\nCredit Card\ncredit card\nDebit Card\nCOD\nCash on Delivery\nMaster Card\nmastercard\nBitcoin"
        )->getJsonLd();

        $this->assertSame('https://example.com/p.html#product', $node['@id']);
        $this->assertSame([
            'http://purl.org/goodrelations/v1#VISA',
            ['@type' => 'CreditCard', 'name' => 'Credit Card'],
            ['@type' => 'PaymentCard', 'name' => 'Debit Card'],
            'http://purl.org/goodrelations/v1#COD',
            'http://purl.org/goodrelations/v1#MasterCard',
        ], $node['offers']['acceptedPaymentMethod']);
    }

    public function testNoProductGivesNoNode(): void
    {
        $this->assertSame([], $this->provider([], 'Visa')->getJsonLd());
    }
}
