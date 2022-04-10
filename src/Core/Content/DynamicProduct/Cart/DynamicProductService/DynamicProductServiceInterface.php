<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;

use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use LineItemDynamicProductCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;

interface DynamicProductServiceInterface
{
    /**
     * Creates @param DynamicProductCollection $dynamicProductCollection
     * @link DynamicProductEntity array, that can be accessed
     * with the lineItemId and saves it to the @link CartDataCollection
     * @return LineItemDynamicProductCollection<string, DynamicProductCollection>
     */
    public function createLineItemDynamicProductCollection(
        DynamicProductCollection $dynamicProductCollection
    ): LineItemDynamicProductCollection;

    public function getFromCartDataByLineItemId(
        string $lineItemId,
        CartDataCollection $data
    ): ?DynamicProductCollection;

    public function removeDynamicProductsFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): void;
}