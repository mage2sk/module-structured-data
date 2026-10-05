<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData;

use Panth\StructuredData\Api\StructuredDataProviderInterface;
use Panth\StructuredData\Helper\Config;
use Psr\Log\LoggerInterface;

class Aggregator
{
    private const PRESERVED_TYPES = ['SoftwareApplication', 'ProductGroup'];

    private const OFFER_TYPES = ['Offer', 'AggregateOffer'];

    private const PRODUCT_TYPES = ['Product', 'SoftwareApplication'];

    private const PRODUCT_ID_SUFFIX = '#product';

    private array $providers;

    public function __construct(
        array $providers,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly ?Validator $validator = null
    ) {
        $this->providers = [];
        foreach ($providers as $key => $provider) {
            if ($provider instanceof StructuredDataProviderInterface) {
                $this->providers[(string) $key] = $provider;
            }
        }
    }

    public function build(): string
    {
        if (!$this->config->isEnabled()) {
            return '';
        }

        $graph = [];
        $seen  = [];
        $anonymous = 0;
        foreach ($this->providers as $code => $provider) {
            try {
                if (!$provider->isApplicable()) {
                    continue;
                }
                if (!$this->config->isStructuredDataEnabled($provider->getCode())) {
                    continue;
                }
                $node = $provider->getJsonLd();
                if ($node === []) {
                    continue;
                }

                $nodes = $this->isList($node) ? $node : [$node];
                foreach ($nodes as $item) {
                    if (!is_array($item) || !isset($item['@type'])) {
                        continue;
                    }
                    $id = isset($item['@id']) && is_string($item['@id']) && $item['@id'] !== ''
                        ? $item['@id']
                        : '_:node' . (++$anonymous);
                    if (isset($seen[$id])) {
                        $type = $seen[$id]['@type'];
                        $seen[$id] = $this->merge($seen[$id], $item);
                        if (in_array($type, self::PRESERVED_TYPES, true)) {
                            $seen[$id]['@type'] = $type;
                        }
                        continue;
                    }
                    $seen[$id] = $item;
                }
            } catch (\Throwable $e) {
                $this->logger->warning(
                    sprintf('[Panth_StructuredData] provider "%s" failed: %s', $code, $e->getMessage()),
                    ['exception' => $e]
                );
            }
        }

        $seen = $this->dropOrphanProductFragments($seen);
        if ($seen === []) {
            return '';
        }

        foreach ($seen as $node) {
            $node = $this->removePricelessOffers($node);
            if ($node === null) {
                continue;
            }
            $graph[] = $node;
        }

        if ($graph === []) {
            return '';
        }

        $doc = count($graph) === 1
            ? array_merge(['@context' => 'https://schema.org'], $graph[0])
            : ['@context' => 'https://schema.org', '@graph' => $graph];

        if ($this->config->isDebug() && $this->validator !== null) {
            $errors = $this->validator->validate($doc);
            if ($errors !== []) {
                $this->logger->info(
                    '[Panth_StructuredData] JSON-LD validation issues: ' . implode('; ', $errors)
                );
            }
        }

        $json = json_encode(
            $doc,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return '';
        }

        return $json;
    }

    private function dropOrphanProductFragments(array $seen): array
    {
        if ($this->config->isStructuredDataEnabled('product')) {
            return $seen;
        }

        $dropped = [];
        foreach ($seen as $id => $node) {
            $type = $node['@type'] ?? '';
            if (is_string($type)
                && in_array($type, self::PRODUCT_TYPES, true)
                && str_ends_with((string) $id, self::PRODUCT_ID_SUFFIX)
            ) {
                $dropped[(string) $id] = true;
                unset($seen[$id]);
            }
        }
        if ($dropped === []) {
            return $seen;
        }

        foreach ($seen as $id => $node) {
            $reviewed = $node['itemReviewed']['@id'] ?? null;
            if (is_string($reviewed) && isset($dropped[$reviewed])) {
                unset($seen[$id]);
            }
        }

        return $seen;
    }

    private function removePricelessOffers(array $node): ?array
    {
        $type = $node['@type'] ?? '';
        if (is_string($type) && in_array($type, self::OFFER_TYPES, true)) {
            return $this->cleanOffer($node);
        }

        if (array_key_exists('offers', $node)) {
            $offers = $this->cleanOffers($node['offers']);
            if ($offers === null) {
                unset($node['offers']);
            } else {
                $node['offers'] = $offers;
            }
        }

        if (isset($node['hasVariant']) && is_array($node['hasVariant'])) {
            $variants = $this->isList($node['hasVariant']) ? $node['hasVariant'] : [$node['hasVariant']];
            foreach ($variants as $index => $variant) {
                if (is_array($variant) && array_key_exists('offers', $variant)) {
                    $offers = $this->cleanOffers($variant['offers']);
                    if ($offers === null) {
                        unset($variants[$index]['offers']);
                    } else {
                        $variants[$index]['offers'] = $offers;
                    }
                }
            }
            $node['hasVariant'] = array_values($variants);
        }

        if (array_diff(array_keys($node), ['@type', '@id']) === []) {
            return null;
        }

        return $node;
    }

    private function cleanOffers(mixed $offers): ?array
    {
        if (!is_array($offers) || $offers === []) {
            return null;
        }

        if (!$this->isList($offers)) {
            return $this->cleanOffer($offers);
        }

        $kept = [];
        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }
            $offer = $this->cleanOffer($offer);
            if ($offer !== null) {
                $kept[] = $offer;
            }
        }

        return $kept === [] ? null : $kept;
    }

    private function cleanOffer(array $offer): ?array
    {
        if (($offer['@type'] ?? '') === 'AggregateOffer') {
            if (array_key_exists('offers', $offer)) {
                $children = $this->cleanOffers($offer['offers']);
                if ($children === null) {
                    unset($offer['offers']);
                } else {
                    $offer['offers'] = $children;
                }
            }

            return $this->hasPrice($offer['lowPrice'] ?? null) ? $offer : null;
        }

        if ($this->hasPrice($offer['price'] ?? null)) {
            return $offer;
        }

        $spec = $offer['priceSpecification'] ?? null;
        if (is_array($spec) && $this->hasPrice($spec['price'] ?? null)) {
            return $offer;
        }

        return null;
    }

    private function hasPrice(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && is_numeric(trim($value));
    }

    private function isList(array $array): bool
    {
        if ($array === []) {
            return false;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }

    private function merge(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (array_key_exists($k, $a) && is_array($a[$k]) && is_array($v)) {
                $a[$k] = $this->merge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }
        return $a;
    }
}
