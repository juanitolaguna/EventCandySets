<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Collections\DynamicProductPayloadCollection;

use EventCandy\Sets\Core\Checkout\Cart\Collections\AbstractCollection\AbstractCollectionInterface;
use EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadStructCollection;

interface DynamicProductPayloadCollectionInterface extends AbstractCollectionInterface
{
    public function set(string $dynamicProductId, PayloadStructCollection $payload);

    public function get(string $dynamicProductId): ?PayloadStructCollection;
}