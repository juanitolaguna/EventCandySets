<?php

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Test implements CartHandlerBundle
{

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        // TODO: Implement collect() method.
    }

    public function saveDynamicProductsBeforeCollect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        // TODO: Implement saveDynamicProductsBeforeCollect() method.
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        // TODO: Implement process() method.
    }

    public function supports(string $type): bool
    {
        // TODO: Implement supports() method.
    }

    public function create(array $data, SalesChannelContext $context): LineItem
    {
        // TODO: Implement create() method.
    }

    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        // TODO: Implement update() method.
    }
}