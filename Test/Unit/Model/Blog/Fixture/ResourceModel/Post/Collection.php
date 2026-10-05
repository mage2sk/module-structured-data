<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\Blog\Fixture\ResourceModel\Post;

use Magento\Framework\DataObject;

class Collection implements \IteratorAggregate
{
    public array $calls = [];

    public function __construct(
        private readonly array $items = [],
        private readonly string $mainTable = '',
        private readonly ?object $resource = null
    ) {
    }

    public function addStoreFilter(int $storeId): self
    {
        $this->calls[] = ['addStoreFilter', $storeId];

        return $this;
    }

    public function addFieldToFilter(string $field, mixed $value): self
    {
        $this->calls[] = ['addFieldToFilter', $field, $value];

        return $this;
    }

    public function addActiveFilter(): self
    {
        $this->calls[] = ['addActiveFilter'];

        return $this;
    }

    public function setPageSize(int $size): self
    {
        $this->calls[] = ['setPageSize', $size];

        return $this;
    }

    public function getMainTable(): string
    {
        return $this->mainTable;
    }

    public function getResource(): ?object
    {
        return $this->resource;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public static function resource(string $idField, string $mainTable): DataObject
    {
        return new class (['id_field_name' => $idField, 'main_table' => $mainTable]) extends DataObject {
            public function getIdFieldName(): string
            {
                return (string) $this->getData('id_field_name');
            }

            public function getMainTable(): string
            {
                return (string) $this->getData('main_table');
            }
        };
    }
}
