<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CollectorOptimizer;

use EventCandy\Sets\Core\Checkout\Cart\SetProductCartCollector;
use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetProductCollectorOptimizer implements CollectorOptimizerInterface
{
    /**
     * @var DynamicProductService
     */
    private $dynamicProductService;

    /**
     * @param DynamicProductService $dynamicProductService
     */
    public function __construct(DynamicProductService $dynamicProductService)
    {
        $this->dynamicProductService = $dynamicProductService;
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

        $dynamicProducts = $this->dynamicProductService->createDynamicProductCollection(
            $lineItems,
            $context->getToken()
        );

        $this->dynamicProductService->saveDynamicProductsToDb($dynamicProducts);
    }
}