<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartHandler\Utils;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class CartChangesObserver
{
    public static function cartHasChanges(Cart $cart): bool
    {
        $lineItems = $cart->getLineItems()->getElements();

        $includesCreditLineItem = array_filter($lineItems, function (LineItem $lineItem) {
            return $lineItem->getType() === LineItem::CREDIT_LINE_ITEM_TYPE;
        });

        if($includesCreditLineItem) {
            return false;
        }

        $areModified = array_filter($lineItems, function (LineItem $lineItem) {
            return $lineItem->isModified();
        });

        // If one Item is modified recalculate all.
        if (count($areModified) > 0) {
            return true;
        }

        // No items modified but one deleted
        if ($cart->isModified()) {
            return true;
        }

        return false;
    }
}