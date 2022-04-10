<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Collections\AbstractCollection;

abstract class AbstractCollection
{
    protected array $elements = [];

    public function getElements(): array
    {
        return $this->elements;
    }

    public function has($key): bool
    {
        return \array_key_exists($key, $this->elements);
    }
}