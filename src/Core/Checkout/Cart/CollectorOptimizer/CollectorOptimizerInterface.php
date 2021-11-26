<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CollectorOptimizer;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CollectorOptimizerInterface
{
    /**
     * Before the processors for set products are triggered, dynamic products has to be created for correct stock calculations.
     * The CollectorOptimizer implements the common CartDataCollectorInterface, but is triggered only once.
     * The CollectorOptimizer also creates and saves the cart if it still does not exist,
     * so related dynamicProducts can be created in the database.
     * Old dynamic products are always deleted based on the session token ($context->getToken) before creating new dynamicProducts.
     * @param CartDataCollection $data
     * @param Cart $original
     * @param SalesChannelContext $context
     * @param CartBehavior $behavior
     */
    public function saveDynamicProductsBeforeCollect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ) :void;
}