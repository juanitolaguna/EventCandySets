<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\OrderLineItemProduct;


use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderLineItemProductEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var OrderLineItemProductEntity|null
     */
    protected $parent;

    /**
     * @var OrderLineItemProductCollection|null
     */
    protected $children;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var OrderEntity
     */
    protected $order;

    /**
     * @var string
     */
    protected $orderLineItemId;

    /**
     * @var OrderLineItemEntity
     */
    protected $orderLineItem;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var ProductEntity
     */
    protected $product;

    /**
     * @return string|null
     */
    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * @param string|null $parentId
     */
    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    /**
     * @return OrderLineItemProductEntity|null
     */
    public function getParent(): ?OrderLineItemProductEntity
    {
        return $this->parent;
    }

    /**
     * @param OrderLineItemProductEntity|null $parent
     */
    public function setParent(?OrderLineItemProductEntity $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return OrderLineItemProductCollection|null
     */
    public function getChildren(): ?OrderLineItemProductCollection
    {
        return $this->children;
    }

    /**
     * @param OrderLineItemProductCollection|null $children
     */
    public function setChildren(?OrderLineItemProductCollection $children): void
    {
        $this->children = $children;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @param string $orderId
     */
    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return OrderEntity
     */
    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    /**
     * @param OrderEntity $order
     */
    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    /**
     * @return string
     */
    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    /**
     * @param string $orderLineItemId
     */
    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    /**
     * @return OrderLineItemEntity
     */
    public function getOrderLineItem(): OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    /**
     * @param OrderLineItemEntity $orderLineItem
     */
    public function setOrderLineItem(OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @param string $productId
     */
    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return ProductEntity
     */
    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    /**
     * @param ProductEntity $product
     */
    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }
}