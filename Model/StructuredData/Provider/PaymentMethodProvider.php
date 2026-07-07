<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData\Provider;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Helper\Config;

class PaymentMethodProvider extends AbstractProvider
{
    private const PAYMENT_METHOD_MAP = [
        'cash'                       => 'http://purl.org/goodrelations/v1#Cash',
        'cash on delivery'           => 'http://purl.org/goodrelations/v1#COD',
        'cod'                        => 'http://purl.org/goodrelations/v1#COD',
        'check'                      => 'http://purl.org/goodrelations/v1#CheckInAdvance',
        'cheque'                     => 'http://purl.org/goodrelations/v1#CheckInAdvance',
        'check in advance'           => 'http://purl.org/goodrelations/v1#CheckInAdvance',
        'credit card'                => 'http://purl.org/goodrelations/v1#ByBankTransferInAdvance',
        'debit card'                 => 'http://purl.org/goodrelations/v1#ByBankTransferInAdvance',
        'bank transfer'              => 'http://purl.org/goodrelations/v1#ByBankTransferInAdvance',
        'wire transfer'              => 'http://purl.org/goodrelations/v1#ByBankTransferInAdvance',
        'invoice'                    => 'http://purl.org/goodrelations/v1#ByInvoice',
        'paypal'                     => 'http://purl.org/goodrelations/v1#PayPal',
        'google pay'                 => 'http://purl.org/goodrelations/v1#GoogleCheckout',
        'google checkout'            => 'http://purl.org/goodrelations/v1#GoogleCheckout',
        'direct debit'               => 'http://purl.org/goodrelations/v1#DirectDebit',
        'visa'                       => 'http://purl.org/goodrelations/v1#VISA',
        'mastercard'                 => 'http://purl.org/goodrelations/v1#MasterCard',
        'master card'                => 'http://purl.org/goodrelations/v1#MasterCard',
        'amex'                       => 'http://purl.org/goodrelations/v1#AmericanExpress',
        'american express'           => 'http://purl.org/goodrelations/v1#AmericanExpress',
        'discover'                   => 'http://purl.org/goodrelations/v1#Discover',
        'jcb'                        => 'http://purl.org/goodrelations/v1#JCB',
        'diners club'                => 'http://purl.org/goodrelations/v1#DinersClub',
    ];

    public function __construct(
        Registry $registry,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Config $config
    ) {
        parent::__construct($registry, $request, $storeManager, $config);
    }

    public function getCode(): string
    {
        return 'paymentMethod';
    }

    public function isApplicable(): bool
    {
        if ($this->getCurrentProduct() === null) {
            return false;
        }

        return $this->getConfiguredMethods() !== [];
    }

    public function getJsonLd(): array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return [];
        }

        $methods = $this->resolvePaymentMethodUris();
        if ($methods === []) {
            return [];
        }

        $url = (string) $product->getProductUrl();

        return [
            '@type' => 'Offer',
            '@id'   => $url . '#offer-payment',
            'acceptedPaymentMethod' => $methods,
        ];
    }

    private function getConfiguredMethods(): array
    {
        $raw = $this->config->getAcceptedPaymentMethods();
        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $raw);
        if ($lines === false) {
            return [];
        }

        $methods = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $methods[] = $trimmed;
            }
        }

        return $methods;
    }

    private function resolvePaymentMethodUris(): array
    {
        $uris = [];
        $seen = [];

        foreach ($this->getConfiguredMethods() as $label) {
            $key = strtolower($label);
            if (!isset(self::PAYMENT_METHOD_MAP[$key])) {
                continue;
            }

            $uri = self::PAYMENT_METHOD_MAP[$key];

            if (isset($seen[$uri])) {
                continue;
            }

            $seen[$uri] = true;
            $uris[] = $uri;
        }

        return $uris;
    }
}
