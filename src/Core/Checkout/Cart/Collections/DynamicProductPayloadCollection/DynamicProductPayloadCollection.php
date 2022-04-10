<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Collections\DynamicProductPayloadCollection;

use EventCandy\Sets\Core\Checkout\Cart\Collections\AbstractCollection\AbstractCollection;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadStructCollection;

class DynamicProductPayloadCollection extends AbstractCollection implements DynamicProductPayloadCollectionInterface
{
    public function set(string $dynamicProductId, PayloadStructCollection $payload)
    {
        $this->elements[$dynamicProductId] = $payload;
    }

    /**
     * @inheritDoc
     */
    public function get(string $dynamicProductId): ?PayloadStructCollection
    {
        if ($this->has($dynamicProductId)) {
            return $this->elements[$dynamicProductId];
        }
        return null;
    }
}