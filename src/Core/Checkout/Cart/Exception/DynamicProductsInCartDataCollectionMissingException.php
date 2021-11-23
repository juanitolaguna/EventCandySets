<?php

namespace EventCandy\Sets\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class DynamicProductsInCartDataCollectionMissingException extends \Exception
{

    public function __construct(LineItem $lineItem, $message = "")
    {
        $message = sprintf("No Products for lineItem: %s | %s in CartDataCollection found! %s", $lineItem->getId() ?? '', $lineItem->getLabel() ?? '', $message);
        parent::__construct($message);
    }
}