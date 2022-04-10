<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandler;

use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer\CartOptimizer\CartOptimizerInterface;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;

/**
 * @internal for reference only, use the extended Interfaces directly.
 * Devs, implementing their own SetProduct processors
 * should implement the Interfaces listed below rather than
 * the @link CartDataCollectorInterface
 * and @link CartProcessorInterface
 *
 * Concepts:
 * DynamicProducts - product of same type may be added multiple times in multiple lineItems.
 * see the ec_dynamic_product table.
 * DynamicProducts are written by classes implementing the @link CartOptimizerInterface
 * Dynamic Products are also used for Payload creation.
 *
 */
interface CartHandlerBundle extends
    LineItemFactoryInterface,
    CartOptimizerInterface,
    AggregateCartCollectorInterface,
    AggregateCartProcessorInterface
{
}
