<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * An AggregateCartOptimizer should bundle the optimizers
 */
interface AggregateCartOptimizerInterface
{
    /**
     * Before the processors for set products are triggered, dynamic products has to be created for correct stock calculations.
     * The CollectorOptimizers are triggered before the AggregateCollectorInterface gets executed.
     * The CollectorOptimizer also creates and saves the cart if it still does not exist,
     * so related dynamicProducts can be created in the database.
     * Old dynamic products are always deleted based on the session token ($context->getToken) before creating new dynamicProducts.
     * @param CartDataCollection $data
     * @param Cart $original
     * @param SalesChannelContext $context
     * @param CartBehavior $behavior
     */
    public function runOptimizers(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ) :void;
}