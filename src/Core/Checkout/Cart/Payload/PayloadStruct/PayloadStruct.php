<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Payload;

class PayloadStruct
{
    private string $dynamicProductId;

    private string $productId;

    private int $quantity;

    private string $name;

    private string $productNumber;

    private float $weight;

    public function __construct(
        string $dynamicProductId,
        string $productId,
        int $quantity,
        string $name,
        string $productNumber,
        float $weight
    ) {
        $this->dynamicProductId = $dynamicProductId;
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->name = $name;
        $this->productNumber = $productNumber;
        $this->weight = $weight;
    }

    public function getDynamicProductId(): string
    {
        return $this->dynamicProductId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }
}
