<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Collections;

use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Framework\Struct\Collection;

class LineItemDynamicProductCollection extends Collection
{
    public function getExpectedClass(): ?string
    {
        return DynamicProductEntity::class;
    }
}