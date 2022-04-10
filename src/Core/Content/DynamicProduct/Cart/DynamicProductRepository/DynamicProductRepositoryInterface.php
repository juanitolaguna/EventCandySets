<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductRepository;

use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductStruct;
use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructCollection\DynamicProductStructCollection;
use EventCandy\Sets\Core\Content\DynamicProductStruct\DynamicProductStructCollection\DynamicProductStructCollectionInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

interface DynamicProductRepositoryInterface
{
    public function saveDynamicProductsToDb(
        DynamicProductStructCollectionInterface $dynamicProducts,
        $isNew = false
    ): void;

    public function removeDynamicProductsByToken(string $token, bool $excludeNew = false): void;

    public function resetNewFlag(string $token): void;

    /**
     * @param array<LineItem> $lineItems
     * @return array<string>
     */
    public function getDynamicProductIds(string $token, array $lineItems): array;
}
