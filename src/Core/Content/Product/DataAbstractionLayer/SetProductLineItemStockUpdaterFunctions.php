<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use EventCandy\Sets\Core\Checkout\Cart\SetProductCartCollector;
use EventCandy\Sets\Utils;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class SetProductLineItemStockUpdaterFunctions implements LineItemStockUpdaterFunctionsInterface
{
    public function getLineItemType(): string
    {
        return SetProductCartCollector::TYPE;
    }

    /**
     * Right now 2 Levels of iteration supported
     * ToDo: recursive for mor flexibility
     * @param OrderLineItemEntity $lineItem
     * @param CheckoutOrderPlacedEvent $event
     * @return array
     */
    public function createOrderLineItemProducts(OrderLineItemEntity $lineItem, CheckoutOrderPlacedEvent $event): array
    {
        /** @var OrderEntity $order */
        $order = $event->getOrder();
        $products = $lineItem->getPayload()[$this->getLineItemType()];
        $payload = [];

        foreach ($products['products'] as $product) {
            $payload[] = [
                'id' => $newParent = Uuid::randomHex(),
                'parentId' => null,
                'productId' => $product['product_id'],
                'orderId' => $order->getId(),
                'orderLineItemId' => $lineItem->getId(),
                'quantity' => $parentQuantity = $product['quantity']
            ];

            $children = $product['products'];
            if (is_array($children) && count($children) > 0) {
                foreach ($children as $child) {
                    $payload[] = [
                        'id' => Uuid::randomHex(),
                        'parentId' => $newParent,
                        'productId' => $child['product_id'],
                        'orderId' => $order->getId(),
                        'orderLineItemId' => $lineItem->getId(),
                        'quantity' => $child['quantity'] * $parentQuantity
                    ];
                }
            }
            $children = null;
        }

        return $payload;
    }

}