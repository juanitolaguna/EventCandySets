<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use EventCandy\Sets\Core\Checkout\Cart\SetProductCartProcessor;

class SetProductLineItemStockUpdaterFunctions implements LineItemStockUpdaterFunctionsInterface
{
    public function getLineItemType(): string
    {
        return SetProductCartProcessor::TYPE;
    }
}