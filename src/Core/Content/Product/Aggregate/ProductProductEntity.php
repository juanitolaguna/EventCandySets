<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Content\Product\Aggregate;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductProductEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $quantity;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var ProductEntity|null
     */
    protected $setProduct;

    /**
     * @return ProductEntity|null
     */
    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    /**
     * @param ProductEntity|null $product
     */
    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    /**
     * @return ProductEntity|null
     */
    public function getSetProduct(): ?ProductEntity
    {
        return $this->setProduct;
    }

    /**
     * @param ProductEntity|null $setProduct
     */
    public function setSetProduct(?ProductEntity $setProduct): void
    {
        $this->setProduct = $setProduct;
    }

    /**
     * @return string
     */
    public function getQuantity(): string
    {
        return $this->quantity;
    }

    /**
     * @param string $quantity
     */
    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
    }



}
