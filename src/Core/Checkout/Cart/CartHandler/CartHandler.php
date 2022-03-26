<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandler;

use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer\AggregateCartOptimizerService;
use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer\CartOptimizer\CartOptimizerInterface;
use EventCandy\Sets\Core\Checkout\Cart\CartHandler\Utils\CartChangesObserver;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This CartHandler is a layer for processing SetProducts.
 * Bundles that are planning to implement their own Collector & Processor Interface
 * for SetProducts should use this layer instead for optimal setproduct stock calculation in runtime.
 *
 * Interfaces to implement - @link CartHandlerBundle
 * @link LineItemFactoryInterface
 * @link CartOptimizerInterface - saves lineItems of the new LineItem type to the DB
 * @link AggregateCartCollectorInterface
 * @link AggregateCartProcessorInterface
 */
class CartHandler implements CartDataCollectorInterface, CartProcessorInterface
{
    private AggregateCartOptimizerService $cartOptimizerService;

    /**
     * @var AggregateCartProcessorInterface[]
     */
    private array $processors;

    /**
     * @var AggregateCartCollectorInterface[]
     */
    private array $collectors;

    /**
     * @param AggregateCartOptimizerService $cartOptimizerService
     * @param AggregateCartProcessorInterface[] $processors
     * @param AggregateCartCollectorInterface[] $collectors
     */
    public function __construct(
        AggregateCartOptimizerService $cartOptimizerService,
        array $processors,
        array $collectors
    ) {
        $this->cartOptimizerService = $cartOptimizerService;
        $this->processors = $processors;
        $this->collectors = $collectors;
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        if (!CartChangesObserver::cartHasChanges($original)) {
            return;
        }

        $data->clear();

        $this->cartOptimizerService->runOptimizers(
            $data,
            $original,
            $context,
            $behavior
        );

        foreach ($this->collectors as $collector) {
            $collector->collect($data, $original, $context, $behavior);
        }
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        foreach ($this->processors as $processor) {
            $processor->process($data, $original, $toCalculate, $context, $behavior);
        }
    }
}
