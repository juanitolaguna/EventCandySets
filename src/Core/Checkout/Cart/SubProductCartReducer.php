<?php

namespace EventCandy\Sets\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubProductCartReducer implements SubProductQuantityInCartReducerInterface
{


    public function reduce(Cart $cart, string $relatedMainId, string $mainProductId, int $subProductQuantity, SalesChannelContext $context = null): int
    {

        $baseLineItemId = null;
        if ($context && $context->getExtension('lineItem')) {
            /** @var LineItem $baseLineItem */
            $baseLineItem = $context->getExtension('lineItem');
            $baseLineItemId = $baseLineItem ? $baseLineItem->getId() : null;
        }


        if ($relatedMainId === $mainProductId && ($baseLineItemId === null)) return 0;
        $lineItems = $cart->getLineItems()->filterFlatByType(SetProductCartProcessor::TYPE);

        if (count($lineItems) == 0) {
            return 0;
        }

        $counter = 0;
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getReferencedId() === $relatedMainId) {
                $counter = $lineItem->getQuantity() * $subProductQuantity;
            }
        }

        return $counter;
    }

}
