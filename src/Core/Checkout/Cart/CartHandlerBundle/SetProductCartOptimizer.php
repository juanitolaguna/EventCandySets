<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandlerBundle;

use Doctrine\DBAL\Exception;
use EventCandy\Sets\Core\Checkout\Cart\CartHandler\AggregateCartOptimizer\CartOptimizer\CartOptimizerInterface;
use EventCandy\Sets\Core\Checkout\Cart\SetProductCartCollector;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductRepositoryInterface;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetProductCartOptimizer implements CartOptimizerInterface
{

    private DynamicProductService $dynamicProductService;

    private DynamicProductRepositoryInterface $dynamicProductRepository;

    public function __construct(DynamicProductService $dynamicProductService,
        DynamicProductRepositoryInterface $dynamicProductRepository
    )
    {
        $this->dynamicProductService = $dynamicProductService;
        $this->dynamicProductRepository = $dynamicProductRepository;
    }

    /**
     * @throws Exception
     */
    public function saveDynamicProductsBeforeCollect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original
            ->getLineItems()
            ->filterFlatByType(SetProductCartCollector::TYPE);

        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection(
            $lineItems,
            $context->getToken()
        );
        $this->dynamicProductRepository->saveDynamicProductsToDb($dynamicProducts);
    }
}
