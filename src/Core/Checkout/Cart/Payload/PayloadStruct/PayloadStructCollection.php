<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Payload;

use Exception;
use Generator;
use Traversable;

class PayloadStructCollection implements PayloadStructCollectionInteface
{
    private array $elements = [];

    /**
     * @inheritDoc
     */
    public function getIterator(): Generator
    {
        yield from $this->elements;
    }

    public function add(PayloadStruct $payloadStruct): void
    {
        $this->elements[] = $payloadStruct;
    }

    public function getElements(): array
    {
        return $this->elements;
    }
}