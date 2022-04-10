<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Collections\AbstractCollection;

interface AbstractCollectionInterface
{
    public function getElements(): ?array;

    public function has(string $key): bool;
}