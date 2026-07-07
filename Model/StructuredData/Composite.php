<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData;

use Panth\StructuredData\Api\StructuredDataProviderInterface;

class Composite extends Aggregator implements StructuredDataProviderInterface
{
    public function isApplicable(): bool
    {
        return true;
    }

    public function getJsonLd(): array
    {
        $json = $this->build();
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getCode(): string
    {
        return 'composite';
    }
}
