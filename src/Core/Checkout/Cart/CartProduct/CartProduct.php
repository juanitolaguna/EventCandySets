<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\CartProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Struct\Struct;

class CartProduct extends Struct
{
    use EntityIdTrait;

    protected string $uniqueId;

    protected string $token;

    protected string $lineItemId;

    protected string $productId;

    protected string $subProductId;

    protected int $subProductQuantity;

    protected int $lineItemQuantity;

    protected string $lineItemType;

    public function __construct(
        string $uniqueId,
        string $token,
        string $lineItemId,
        string $productId,
        string $subProductId,
        int $subProductQuantity,
        int $lineItemQuantity,
        string $lineItemType
    ) {
        $this->uniqueId = $uniqueId;
        $this->token = $token;
        $this->lineItemId = $lineItemId;
        $this->productId = $productId;
        $this->subProductId = $subProductId;
        $this->subProductQuantity = $subProductQuantity;
        $this->lineItemQuantity = $lineItemQuantity;
        $this->lineItemType = $lineItemType;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getSubProductId(): string
    {
        return $this->subProductId;
    }

    public function getSubProductQuantity(): int
    {
        return $this->subProductQuantity;
    }

    public function getLineItemQuantity(): int
    {
        return $this->lineItemQuantity;
    }

    public function getLineItemType(): string
    {
        return $this->lineItemType;
    }
}