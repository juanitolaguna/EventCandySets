<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\DataAbstractionLayer;

use EventCandy\Sets\Core\Checkout\Cart\SetProductCartCollector;
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


    public function createOrderLineItemProducts(OrderLineItemEntity $lineItem, CheckoutOrderPlacedEvent $event): array
    {
        /** @var OrderEntity $order */
        $order = $event->getOrder();
        $products = $lineItem->getPayload()[$this->getLineItemType()];
        return $this->createOrderLineItemsRecursive($products['products'], $lineItem, $order);

    }

    private function createOrderLineItemsRecursive(
        array $products,
        OrderLineItemEntity $lineItem,
        OrderEntity $order,
        string $parentId = null
    ): array {
        $orderLineItem = [];
        foreach ($products as $product) {
            $orderLineItem[] = [
                'id' => $newParent = Uuid::randomHex(),
                'parentId' => $parentId,
                'productId' => $product['product_id'],
                'orderId' => $order->getId(),
                'orderLineItemId' => $lineItem->getId(),
                'quantity' => $product['quantity'] * $lineItem->getQuantity()
            ];

            if (is_array($product['products']) && count($product['products']) > 0) {@
                $orderLineItem = array_merge(
                    $orderLineItem,
                    $this->createOrderLineItemsRecursive($product['products'], $lineItem, $order, $newParent ?? null)
                );
            }
        }
        return $orderLineItem;
    }


}