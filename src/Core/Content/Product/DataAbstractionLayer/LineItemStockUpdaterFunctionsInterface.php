<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

interface LineItemStockUpdaterFunctionsInterface
{
    public function getLineItemType(): string;


    /**
     * @param OrderLineItemEntity $lineItem
     * @param CheckoutOrderPlacedEvent $event
     * @return array
     *
     * [[
     * 'id' => Uuid::randomHex(),
     * 'parentId' => '' ,
     * 'productId' => '',
     * 'orderId' => '',
     * 'orderLineItemId' => '',
     * 'quantity' => x
     * ],
     * ['id' => ...]]
     */
    public function createOrderLineItemProducts(OrderLineItemEntity $lineItem, CheckoutOrderPlacedEvent $event): array;
}
