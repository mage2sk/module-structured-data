<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\Blog\Fixture\ResourceModel\Post;

class CollectionFactory
{
    public function __construct(
        private readonly ?Collection $collection = null,
        private readonly ?\Throwable $error = null
    ) {
    }

    public function create(): Collection
    {
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->collection ?? new Collection();
    }
}
