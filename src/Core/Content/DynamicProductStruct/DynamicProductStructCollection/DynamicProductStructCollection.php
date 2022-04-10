<?php

namespace EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructCollection;

use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStruct;
use Generator;

class DynamicProductStructCollection implements DynamicProductStructCollectionInterface
{
    /**
     * @var array<DynamicProductStruct>
     */
    private array $elements;

    public function add(DynamicProductStruct $product): void
    {
        $this->elements[] = $product;
    }

    public function getIterator(): Generator
    {
        yield from $this->elements;
    }

    /**
     * @inheritDoc
     */
    public function getElements(): array
    {
        return $this->elements;
    }
}