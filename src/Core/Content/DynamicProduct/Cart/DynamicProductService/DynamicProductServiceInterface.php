<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;

use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProduct;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductEntity;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

interface DynamicProductServiceInterface
{
    /**
     * This method can be overwritten by an extending class if LineItem contains more than one product.
     * @param array<LineItem> $lineItems
     * @return array<DynamicProduct>
     */
    public function createDynamicProductCollection(array $lineItems, string $token): array;

    /**
     * @param array<DynamicProduct> $dynamicProducts
     * @return array<string>
     */
    public function getDynamicProductIdsFromCollection(array $dynamicProducts): array;

    /**
     * Creates @param DynamicProductCollection $dynamicProductCollection
     * @param CartDataCollection $data
     * @link DynamicProductEntity array, that can be accessed
     * with the lineItemId and saves it to the @link CartDataCollection
     */
    public function addDynamicProductsToCartDataByLineItemId(
        DynamicProductCollection $dynamicProductCollection,
        CartDataCollection $data
    ): void;

    /**
     * @return ?array<DynamicProductEntity>
     */
    public function getFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): ?array;

    public function removeDynamicProductsFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): void;
}