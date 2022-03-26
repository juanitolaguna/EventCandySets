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
 */
interface CartHandlerBundle extends
    LineItemFactoryInterface,
    CartOptimizerInterface,
    AggregateCartCollectorInterface,
    AggregateCartProcessorInterface
{
}
