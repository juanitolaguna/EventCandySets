<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructService;


use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructCollection\DynamicProductStructCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

interface DynamicProductStructServiceInterface
{
    /**
     * This method can be overwritten by an extending class if LineItem contains more than one product.
     * @param array<LineItem> $lineItems
     * @return DynamicProductStructCollection
     */
    public function createDynamicProductStructCollection(
        array $lineItems,
        string $token
    ): DynamicProductStructCollection;

    /**
     * @param DynamicProductStructCollection $dynamicProducts
     * @return array<string>
     */
    public function getDynamicProductIdsFromCollection(DynamicProductStructCollection $dynamicProducts): array;
}