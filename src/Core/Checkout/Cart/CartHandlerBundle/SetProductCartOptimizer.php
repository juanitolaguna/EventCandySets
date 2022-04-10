<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandlerBundle;

use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer\CartOptimizer\CartOptimizerInterface;
use EventCandy\Sets\Core\Checkout\Cart\SetProductCartCollector;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductRepository\DynamicProductRepositoryInterface;
use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructService\DynamicProductStructServiceInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetProductCartOptimizer implements CartOptimizerInterface
{

    private DynamicProductStructServiceInterface $dynamicProductStructService;

    private DynamicProductRepositoryInterface $dynamicProductRepository;

    public function __construct(
        DynamicProductStructServiceInterface $dynamicProductStructService,
        DynamicProductRepositoryInterface $dynamicProductRepository
    ) {
        $this->dynamicProductStructService = $dynamicProductStructService;
        $this->dynamicProductRepository = $dynamicProductRepository;
    }

    public function saveDynamicProductsBeforeCollect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original
            ->getLineItems()
            ->filterFlatByType(SetProductCartCollector::TYPE);

        $this->saveDynamicProducts($lineItems, $context);
    }

    private function saveDynamicProducts(array $lineItems, SalesChannelContext $context): void
    {
        $dynamicProducts = $this->dynamicProductStructService->createDynamicProductStructCollection(
            $lineItems,
            $context->getToken()
        );
        $this->dynamicProductRepository->saveDynamicProductsToDb($dynamicProducts);
    }
}