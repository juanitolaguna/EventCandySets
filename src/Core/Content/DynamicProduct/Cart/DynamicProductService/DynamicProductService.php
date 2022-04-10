<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;

use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use LineItemDynamicProductCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;

class DynamicProductService implements DynamicProductServiceInterface
{
    private const DYNAMIC_PRODUCT_LINE_ITEM_ID = 'dynamic_product_line_item_id-';

    /**
     * @inheritDoc
     */
    public function createLineItemDynamicProductCollection(
        DynamicProductCollection $dynamicProductCollection
    ): LineItemDynamicProductCollection {
        $collection = new LineItemDynamicProductCollection();

        foreach ($dynamicProductCollection as $product) {
            $key = self::lineItemKey(
                $product->getLineItemId()
            );

            if ($collection->has($key)) {
                /** @var DynamicProductCollection $dynamicProducts */
                $dynamicProducts= $collection->get($key);
                $dynamicProducts->add($product);
                // Do I need that?
                $collection->set($key, $dynamicProducts);
            } else {
                $dynamicProducts = new DynamicProductCollection([$product]);
                $collection->set($key, $dynamicProducts);
            }
        }
        return $collection;
    }


    public function getFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): ?DynamicProductCollection
    {
        return $data->get(
            self::lineItemKey($lineItemId)
        );
    }

    public function removeDynamicProductsFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): void
    {
        $data->remove(
            self::lineItemKey($lineItemId)
        );
    }

    public static function lineItemKey(string $lineItemId): string
    {
        return self::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItemId;
    }
}