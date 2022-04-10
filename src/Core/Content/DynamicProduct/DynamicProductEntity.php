<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\DynamicProduct;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DynamicProductEntity extends Entity
{
    use EntityIdTrait;

    protected string $token;

    protected string $productId;

    protected ?ProductEntity $product;

    protected string $lineItemId;

    protected ?bool $isNew;

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

    public function setLineItemId(string $lineItemId): void
    {
        $this->lineItemId = $lineItemId;
    }

    public function getIsNew(): ?bool
    {
        return $this->isNew;
    }

    public function setIsNew(?bool $isNew): void
    {
        $this->isNew = $isNew;
    }
}
