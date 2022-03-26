<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Payload\PayloadRepository;

use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PayloadRepositoryInterface
{
    /**
     * Fetches the required data to build the subproducts payload and saves it to the CartDataCollection.
     * For Performance gain same method is used to fetch data to build the CartProduct collection.
     * Hence 'subproducts' are needed for 2 Different DataStructures but are fetched only once from Db.
     * @throws Exception
     */
    public function loadPayloadDataForLineItem(
        LineItem $lineItem,
        CartDataCollection $data,
        SalesChannelContext $context
    ): void;
}