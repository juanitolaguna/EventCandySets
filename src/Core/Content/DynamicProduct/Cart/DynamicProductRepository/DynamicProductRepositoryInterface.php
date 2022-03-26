<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProductRepository;

use EventCandy\Sets\Core\Content\DynamicProduct\Cart\DynamicProduct;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

interface DynamicProductRepositoryInterface
{
    /**
     * @param array<DynamicProduct> $dynamicProducts
     */
    public function saveDynamicProductsToDb(array $dynamicProducts, $isNew = false): void;

    public function removeDynamicProductsByToken(string $token, bool $excludeNew = false): void;

    public function resetNewFlag(string $token): void;

    /**
     * @param array<LineItem> $lineItems
     * @return array<string>
     */
    public function getDynamicProductIds(string $token, array $lineItems): array;
}
