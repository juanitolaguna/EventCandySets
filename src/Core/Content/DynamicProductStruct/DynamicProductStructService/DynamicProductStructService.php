<?php

namespace EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructService;

use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStruct;
use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructCollection\DynamicProductStructCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class DynamicProductStructService implements DynamicProductStructServiceInterface
{

    /**
     * @inheritDoc
     */
    public function createDynamicProductStructCollection(
        array $lineItems,
        string $token
    ): DynamicProductStructCollection {
        $collection = new DynamicProductStructCollection();
        foreach ($lineItems as $lineItem) {
            $id = Uuid::randomHex();
            $product = new DynamicProductStruct(
                $id,
                $token,
                $lineItem->getReferencedId(),
                $lineItem->getId()
            );
            $collection->add($product);
        }
        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function getDynamicProductIdsFromCollection(DynamicProductStructCollection $dynamicProducts): array
    {
        return array_map(function (DynamicProductStruct $product) {
            return $product->getId();
        }, $dynamicProducts->getElements());
    }
}