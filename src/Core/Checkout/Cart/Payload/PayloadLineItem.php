<?php

declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Payload;

class PayloadLineItem
{
    /**
     * @var string
     */
    protected $label;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var PayloadLineItemProduct[]
     */
    protected $products = [];

    /**
     * @param string $label
     * @param int $quantity
     */
    public function __construct(string $label, int $quantity)
    {
        $this->label = $label;
        $this->quantity = $quantity;
    }

    /**
     * @return float
     */
    public function getTotalWeight(): float
    {
        $weight = 0.0;
        if (is_null($this->products)) {
            return $weight;
        }

        foreach ($this->products as $product) {
            $weight += $product->getWeight() * $this->getQuantity();
        }
        return $weight;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return PayloadLineItemProduct[]
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @param PayloadLineItemProduct[] $products
     */
    public function setProducts(array $products): void
    {
        $this->products = $products;
    }

    
    public function addProduct(PayloadLineItemProduct $product)
    {
        $this->products[] = $product;
    }


}