<?php

namespace EventCandy\Sets\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SubProductQuantityInCartReducerInterface {
    public function reduce(Cart $cart, string $relatedMainId, string $mainProductId, int $subProductQuantity, SalesChannelContext $context = null): int;
}
