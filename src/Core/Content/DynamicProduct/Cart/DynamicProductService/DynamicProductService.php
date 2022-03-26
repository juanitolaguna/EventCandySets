<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductService;

use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProduct;
use EventCandy\Sets\Core\Content\DynamicProduct\DynamicProductCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class DynamicProductService implements DynamicProductServiceInterface
{
    private const DYNAMIC_PRODUCT_LINE_ITEM_ID = 'dynamic_product_line_item_id-';

    /**
     * @inheritDoc
     */
    public function createDynamicProductCollection(array $lineItems, string $token): array
    {
        $collection = [];
        foreach ($lineItems as $lineItem) {
            $id = Uuid::randomHex();
            $collection[] = new DynamicProduct(
                $id,
                $token,
                $lineItem->getReferencedId(),
                $lineItem->getId()
            );
        }
        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function getDynamicProductIdsFromCollection(array $dynamicProducts): array
    {
        return array_map(function (DynamicProduct $product) {
            return $product->getId();
        }, $dynamicProducts);
    }

    /**
     * @inheritDoc
     */
    public function addDynamicProductsToCartDataByLineItemId(
        DynamicProductCollection $dynamicProductCollection,
        CartDataCollection $data
    ): void {

        foreach ($dynamicProductCollection as $product) {
            $lineItemId = $product->getLineItemId();
            $key = self::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItemId;

            if ($data->has($key)) {
                $products = $data->get($key);
                $products[] = $product;
                $data->set($key, $products);
            } else {
                $data->set($key, [$product]);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): ?array
    {
        $key = self::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItemId;
        return $data->get($key);
    }

    public function removeDynamicProductsFromCartDataByLineItemId(string $lineItemId, CartDataCollection $data): void
    {
        $key = self::DYNAMIC_PRODUCT_LINE_ITEM_ID . $lineItemId;
        $data->remove($key);
    }
}