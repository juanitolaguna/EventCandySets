<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use EventCandy\Sets\Core\Checkout\Cart\SetProductCartProcessor;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class SetProductLineItemStockUpdaterFunctions implements LineItemStockUpdaterFunctionsInterface
{
    public function getLineItemType(): string
    {
        return SetProductCartProcessor::TYPE;
    }


    public function createOrderLineItemProducts(OrderLineItemEntity $lineItem, CheckoutOrderPlacedEvent $event): array
    {
        $order = $event->getOrder();
        $lineItemQuantity = $lineItem->getQuantity();

        $orderLineItems = [];

        // Main Product
        $mainOrderLineItemId = Uuid::randomHex();
        $orderLineItems[] = [
                'id' => $mainOrderLineItemId,
                'productId' => $lineItem->getReferencedId(),
                'orderId' => $order->getId(),
                'orderLineItemId' => $lineItem->getId(),
                'quantity' => $lineItemQuantity
        ];

        $subProducts = $lineItem->getPayload()[$this->getLineItemType()];

        foreach ($subProducts as $product) {
            $orderLineItems[] = [
                'id' => Uuid::randomHex(),
                'parentId' => $mainOrderLineItemId,
                'productId' => $product['product_id'],
                'orderId' => $order->getId(),
                'orderLineItemId' => $lineItem->getId(),
                'quantity' => $product['quantity'] * $lineItemQuantity
            ];
        }

        return $orderLineItems;
    }

}