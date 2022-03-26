<?php

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer\CartOptimizer;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartOptimizerInterface
{
    public function saveDynamicProductsBeforeCollect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ) :void;
}