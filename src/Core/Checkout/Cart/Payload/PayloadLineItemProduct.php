<?php
declare(strict_types=1);

namespace EventCandy\Sets\Core\Checkout\Cart\Payload;

class PayloadLineItemProduct
{
    /**
     * @var string
     */
    protected $productNumber;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var float
     */
    protected $weight;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var PayloadLineItemProduct[]|null
     */
    protected $products;


    /**
     * @param string $productNumber
     * @param string $productId
     * @param string $name
     * @param float $weight
     * @param int $quantity
     * @param array|null $products
     */
    public function __construct(
        string $productNumber,
        string $productId,
        string $name,
        float $weight,
        int $quantity,
        ?array $products
    ) {
        $this->productNumber = $productNumber;
        $this->productId = $productId;
        $this->name = $name;
        $this->weight = $weight;
        $this->products = $products;
        $this->quantity = $quantity;
        $this->products = $products;
    }

    /**
     * @return string
     */
    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Ignores the Main Product Weight - sums the weight of the children
     * @return float
     */
    public function getWeight(): float
    {
        if (!is_null($this->products)) {
            return $this->weight;
        }
        $weight = 0;

        /** @var self $product */
        foreach ($this->products as $product) {
            $weight += $product->getWeight() * $product->getQuantity() * $this->getQuantity();
        }
        return $weight;
    }

    /**
     * @return PayloadLineItemProduct[]|null
     */
    public function getProducts(): ?array
    {
        return $this->products;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }


}